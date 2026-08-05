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
    private const TRACKING_SESSION_TTL_SECONDS = 1800;

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

        session([
            'track_report_id' => $report->id,
            'track_access_ok' => true,
            'track_verified_at' => now()->timestamp,
        ]);

        return view('public.track-result', ['report' => $report]);
    }

    public function addInfo(Request $request, Report $report): RedirectResponse
    {
        if (! $this->hasTrackingAccess($report)) {
            return redirect()
                ->route('track.form')
                ->withErrors(['access_code' => 'Sesi tracking sudah habis. Masukkan nomor laporan dan kode akses lagi.']);
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
            $report->update(['status' => 'dibuka_kembali']);
            ReportStatusHistory::create([
                'report_id' => $report->id,
                'actor_type' => 'reporter',
                'previous_status' => $old,
                'new_status' => 'dibuka_kembali',
                'public_note' => $old === 'menunggu_konfirmasi'
                    ? 'Pelapor memberi catatan bahwa laporan belum selesai dan perlu ditindaklanjuti kembali.'
                    : 'Pelapor menambahkan informasi yang diminta.',
            ]);
        }

        return back()->with('status', 'Informasi tambahan dikirim.');
    }

    public function confirmComplete(Report $report): RedirectResponse
    {
        if (! $this->hasTrackingAccess($report)) {
            return redirect()
                ->route('track.form')
                ->withErrors(['access_code' => 'Sesi tracking sudah habis. Masukkan nomor laporan dan kode akses lagi.']);
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

    private function hasTrackingAccess(Report $report): bool
    {
        $verifiedAt = (int) session('track_verified_at', 0);
        $isFresh = $verifiedAt > 0 && now()->timestamp - $verifiedAt <= self::TRACKING_SESSION_TTL_SECONDS;

        if (! $isFresh) {
            session()->forget(['track_report_id', 'track_access_ok', 'track_verified_at']);

            return false;
        }

        return session('track_report_id') === $report->id && session('track_access_ok') === true;
    }

    private function normalizeReportNumber(string $value): string
    {
        return preg_replace('/[^A-Z0-9-]+/', '', strtoupper(trim($value))) ?? '';
    }

    private function normalizeAccessCode(string $value): string
    {
        return preg_replace('/[^0-9]+/', '', trim($value)) ?? '';
    }
}
