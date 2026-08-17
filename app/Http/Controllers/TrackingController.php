<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportNote;
use App\Models\ReportStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TrackingController extends Controller
{

    public function form(): View
    {
        return view('public.track');
    }

    public function search(Request $request): View|RedirectResponse
    {
        $request->merge([
            'report_number' => $this->normalizeReportNumber((string) $request->input('report_number', '')),
            'access_code' => $this->normalizeAccessCode((string) $request->input('access_code', '')),
        ]);

        $data = $request->validate([
            'report_number' => ['required', 'string', 'regex:/^(?:LPR[0-9]{10}|LAP-[A-Z2-9]{6}-[A-Z2-9]{6})$/'],
            'access_code' => ['required', 'digits:6'],
        ]);

        $report = Report::with(['histories' => fn ($query) => $query->oldest()])
            ->where('report_number', trim($data['report_number']))
            ->first();

        if (! $report || ! Hash::check($data['access_code'], $report->access_code_hash)) {
            return back()->withErrors(['report_number' => 'Nomor laporan atau kode akses tidak valid.']);
        }

        // Tracking access is bound to the current client IP instead of a Laravel session.
        // The report stores only an HMAC hash of the submission IP, never the raw IP.
        if (! $this->deviceMatchesReport($request, $report)) {
            return back()->withErrors(['report_number' => 'Laporan hanya dapat dilacak dari perangkat/jaringan yang digunakan saat laporan dibuat.']);
        }

        $trackingProof = $report->id.'|'.hash_hmac('sha256', (string) $report->access_code_hash, config('app.key'));

        return response()
            ->view('public.track-result', ['report' => $report])
            ->withCookie(cookie(
                'laporin_tracking_proof',
                $trackingProof,
                15,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            ));
    }

    public function addInfo(Request $request, Report $report): RedirectResponse
    {
        if (! $this->deviceMatchesReport($request, $report) || ! $this->trackingProofMatchesReport($request, $report)) {
            return redirect()
                ->route('track.form')
                ->withErrors(['access_code' => 'Akses tracking ditolak karena alamat IP perangkat tidak cocok dengan perangkat saat laporan dibuat.']);
        }

        if (! in_array($report->status, ['memerlukan_informasi', 'dibuka_kembali', 'menunggu_konfirmasi'], true)) {
            return back()->withErrors(['report' => 'Aksi tambah informasi tidak tersedia untuk status laporan saat ini.']);
        }

        $data = $request->validate(['note' => ['required', 'string', 'max:3000']]);
        ReportNote::create([
            'report_id' => $report->id,
            'author_type' => 'reporter',
            'note' => $data['note'],
            'visibility' => 'internal',
        ]);

        if ($report->status !== 'dibuka_kembali') {
            $old = $report->status;
            $note = $old === 'menunggu_konfirmasi'
                ? 'Pelapor memberi catatan bahwa laporan belum selesai dan perlu ditindaklanjuti kembali.'
                : 'Pelapor menambahkan informasi yang diminta.';

            $report->update(['status' => 'dibuka_kembali']);
            ReportStatusHistory::create([
                'report_id' => $report->id,
                'actor_type' => 'reporter',
                'previous_status' => $old,
                'new_status' => 'dibuka_kembali',
                'public_note' => $note,
            ]);

        }

        return back()->with('status', 'Informasi tambahan dikirim.');
    }

    public function confirmComplete(Request $request, Report $report): RedirectResponse
    {
        if (! $this->deviceMatchesReport($request, $report) || ! $this->trackingProofMatchesReport($request, $report)) {
            return redirect()
                ->route('track.form')
                ->withErrors(['access_code' => 'Akses tracking ditolak karena alamat IP perangkat tidak cocok dengan perangkat saat laporan dibuat.']);
        }

        if ($report->status !== 'menunggu_konfirmasi') {
            return back()->withErrors(['report' => 'Laporan belum berada pada tahap menunggu konfirmasi.']);
        }

        $old = $report->status;
        $report->update(['status' => 'selesai']);
        ReportStatusHistory::create([
            'report_id' => $report->id,
            'actor_type' => 'reporter',
            'previous_status' => $old,
            'new_status' => 'selesai',
            'public_note' => 'Pelapor mengonfirmasi selesai.',
        ]);


        return back()->with('status', 'Laporan dikonfirmasi selesai.');
    }

    private function trackingProofMatchesReport(Request $request, Report $report): bool
    {
        $proof = $request->cookie('laporin_tracking_proof');
        if (! is_string($proof) || ! str_contains($proof, '|')) {
            return false;
        }

        [$reportId, $proofHash] = explode('|', $proof, 2);
        if ((int) $reportId !== (int) $report->id || ! preg_match('/^[a-f0-9]{64}$/', $proofHash)) {
            return false;
        }

        // The proof contains only an HMAC of the access code; the access code itself is never stored in the cookie.
        // This prevents a device-only cookie from authorizing state-changing tracking actions.
        // Reconstruct a stable verifier from the stored password hash. The plaintext access code is not retained.
        return hash_equals(
            $proofHash,
            hash_hmac('sha256', (string) $report->access_code_hash, config('app.key'))
        );
    }

    /**
     * Bind public tracking actions to the client IP used when the report was submitted.
     * The database stores an HMAC, so the raw IP is never persisted in the report.
     */
    private function deviceMatchesReport(Request $request, Report $report): bool
    {
        $deviceId = $request->cookie('laporin_device_id');
        if (! is_string($deviceId) || $deviceId === '') {
            return false;
        }

        $deviceHash = hash_hmac('sha256', $deviceId, config('app.key'));
        if (is_string($report->submitted_device_hash) && $report->submitted_device_hash !== '') {
            return hash_equals($report->submitted_device_hash, $deviceHash);
        }

        // Backward compatibility for reports created before device binding existed.
        $ip = $request->ip();
        if (! is_string($ip) || $ip === '' || ! is_string($report->submitted_ip_hash) || $report->submitted_ip_hash === '') {
            return false;
        }
        return hash_equals($report->submitted_ip_hash, hash_hmac('sha256', $ip, config('app.key')));
    }

    private function normalizeReportNumber(string $value): string
    {
        $upper = strtoupper(trim($value));
        $compact = preg_replace('/[^A-Z0-9]+/', '', $upper) ?? '';

        if (str_starts_with($compact, 'LPR')) {
            return $compact;
        }

        if (preg_match('/^LAP([A-Z2-9]{6})([A-Z2-9]{6})$/', $compact, $matches) === 1) {
            return 'LAP-'.$matches[1].'-'.$matches[2];
        }

        return preg_replace('/[^A-Z0-9-]+/', '', $upper) ?? '';
    }

    private function normalizeAccessCode(string $value): string
    {
        return preg_replace('/[^0-9]+/', '', trim($value)) ?? '';
    }
}
