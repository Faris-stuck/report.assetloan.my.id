<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportNote;
use App\Models\ReportStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TrackingController extends Controller
{

    public function form(): View
    {
        return view('public.track');
    }

    public function search(Request $request): RedirectResponse
    {
        $request->merge([
            'report_number' => $this->normalizeReportNumber((string) $request->input('report_number', '')),
            'access_code' => $this->normalizeAccessCode((string) $request->input('access_code', '')),
        ]);

        $data = $request->validate([
            'report_number' => ['required', 'string', 'regex:/^(?:LPR[0-9]{10}|LAP-[A-Z2-9]{6}-[A-Z2-9]{6})$/'],
            'access_code' => ['required', 'digits:6'],
        ]);

        $report = Report::query()
            ->where('report_number', trim($data['report_number']))
            ->first();

        /*
         * Nomor salah, kode akses salah, dan perangkat tidak cocok sengaja
         * memakai satu pesan yang sama. Sebelumnya perangkat yang tidak cocok
         * dijawab dengan pesan tersendiri, sehingga penebak kode akses 6 digit
         * bisa tahu kapan kodenya sudah benar hanya dari perbedaan pesan.
         */
        $invalid = 'Nomor laporan, kode akses, atau perangkat tidak cocok. Laporan hanya dapat dilacak dari perangkat/jaringan yang digunakan saat laporan dibuat.';

        if (! $report || ! Hash::check($data['access_code'], $report->access_code_hash)) {
            return $this->rejectSearch($request, $invalid);
        }

        // Tracking access is bound to the current client IP instead of a Laravel session.
        // The report stores only an HMAC hash of the submission IP, never the raw IP.
        if (! $this->deviceMatchesReport($request, $report)) {
            return $this->rejectSearch($request, $invalid);
        }

        /*
         * Hasil pencarian TIDAK dirender langsung dari POST ini, melainkan
         * dialihkan ke GET `track.result`.
         *
         * Laravel hanya memanggil setPreviousUrl() untuk permintaan GET
         * non-ajax, jadi halaman hasil yang dirender dari POST tidak pernah
         * menjadi "previous URL". Akibatnya `back()` di akhir addInfo() dan
         * confirmComplete() mendarat di GET /lacak yang KOSONG: pelapor membaca
         * "Informasi tambahan dikirim." di atas formulir blanko, lalu harus
         * mengetik ulang nomor laporan dan kode akses hanya untuk melihat
         * laporannya sendiri. Setiap aksi yang berhasil terasa seperti
         * dikeluarkan dari halaman.
         *
         * Dengan pola redirect ini halaman hasil punya URL GET nyata, sehingga
         * `back()` kembali ke tempat yang benar dan menekan muat-ulang tidak
         * lagi mengirim ulang nomor + kode akses.
         */
        return redirect()
            ->route('track.result')
            ->withCookie($this->trackingProofCookie($request, $report));
    }

    /**
     * Halaman hasil pelacakan, dibuka lewat GET.
     *
     * Laporan diambil dari cookie bukti pelacakan, bukan dari URL, supaya nomor
     * laporan tidak pernah tertinggal di riwayat peramban atau header Referer
     * pada perangkat yang dipakai bersama.
     */
    public function result(Request $request): Response|RedirectResponse
    {
        $report = $this->reportFromTrackingProof($request);

        if ($report === null || ! $this->deviceMatchesReport($request, $report)) {
            return redirect()
                ->route('track.form')
                ->withErrors([
                    'report_number' => 'Sesi pelacakan sudah berakhir. Masukkan lagi nomor laporan dan kode akses untuk melihat status laporan.',
                    'access_code' => 'Sesi pelacakan sudah berakhir. Masukkan lagi nomor laporan dan kode akses untuk melihat status laporan.',
                ]);
        }

        $report->load(['histories' => fn ($query) => $query->oldest()]);

        return response()
            ->view('public.track-result', [
                'report' => $report,
                'noteDraft' => $this->pullNoteDraft($report),
            ])
            // Halaman ini memuat isi laporan milik satu pelapor. Tanpa no-store,
            // perangkat yang dipakai bersama bisa menampilkannya kembali lewat
            // tombol Kembali setelah cookie buktinya sudah hangus.
            ->withCookie($this->trackingProofCookie($request, $report))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Kembalikan pelapor ke formulir dengan pesan yang sama pada KEDUA field.
     *
     * Pesannya memang tidak menyebut mana yang salah (lihat $invalid di
     * search()), jadi menempelkannya hanya pada `report_number` membuat pelapor
     * yang salah mengetik kode akses melihat penanda merah di field nomor
     * laporan dan mengedit field yang keliru. Nilai stringnya identik di kedua
     * field, sehingga tidak ada informasi baru yang bocor ke penebak kode.
     */
    private function rejectSearch(Request $request, string $message): RedirectResponse
    {
        return back()
            ->withErrors(['report_number' => $message, 'access_code' => $message])
            ->withInput($request->except('access_code'));
    }

    public function addInfo(Request $request, Report $report): RedirectResponse
    {
        if ($denied = $this->denyReporterAction($request, $report)) {
            return $denied;
        }

        if (! in_array($report->status, ['memerlukan_informasi', 'dibuka_kembali', 'menunggu_konfirmasi'], true)) {
            return redirect()
                ->route('track.result')
                ->withErrors(['report' => 'Aksi tambah informasi tidak tersedia untuk status laporan saat ini.']);
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

        return redirect()
            ->route('track.result')
            ->with('status', 'Informasi tambahan dikirim.');
    }

    public function confirmComplete(Request $request, Report $report): RedirectResponse
    {
        if ($denied = $this->denyReporterAction($request, $report)) {
            return $denied;
        }

        if ($report->status !== 'menunggu_konfirmasi') {
            return redirect()
                ->route('track.result')
                ->withErrors(['report' => 'Laporan belum berada pada tahap menunggu konfirmasi.']);
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


        return redirect()
            ->route('track.result')
            ->with('status', 'Laporan dikonfirmasi selesai.');
    }

    /**
     * Penjagaan bersama untuk dua aksi pelapor. Sebelumnya kedua penyebab
     * digabung dengan OR dan dijawab satu pesan "alamat IP perangkat tidak
     * cocok", padahal penyebab yang paling sering terjadi adalah bukti
     * pelacakan yang hangus setelah 15 menit — dan itu bisa dipulihkan
     * sendiri oleh pelapor dengan mencari ulang laporannya.
     */
    private function denyReporterAction(Request $request, Report $report): ?RedirectResponse
    {
        if (! $this->deviceMatchesReport($request, $report)) {
            /*
             * Tulisan pelapor juga harus diselamatkan di jalur ini. Pelapor yang
             * membuka laporannya di perangkat lain baru mengetahui pembatasannya
             * setelah menekan Kirim, jadi kalau tidak disimpan, catatannya hilang
             * di sini persis seperti pada jalur bukti yang hangus.
             */
            $this->rememberNoteDraft($request, $report);

            /*
             * Jalan keluar WAJIB disebutkan. EnterpriseSecurity menerbitkan
             * laporin_device_id baru untuk setiap permintaan tanpa cookie yang
             * sah, sehingga pelapor yang sekadar menghapus data peramban — hal
             * yang sangat biasa — mendapat identitas perangkat baru yang tidak
             * akan pernah cocok lagi dengan laporannya. Sebelumnya pesan di sini
             * berhenti pada "hanya dapat dilacak dari perangkat yang digunakan
             * saat laporan dibuat": buntu total, tanpa memberi tahu bahwa
             * laporannya tetap diproses dan masih bisa ditanyakan langsung.
             */
            return redirect()
                ->route('track.form')
                ->withErrors(['report_number' => 'Laporan hanya dapat dilacak dan diperbarui dari perangkat serta peramban yang digunakan saat laporan dibuat. Coba buka lagi dari perangkat itu. Jika perangkatnya sudah tidak ada atau data peramban-nya sudah dihapus, laporan Anda tetap diproses — bawa nomor laporan Anda ke ruang Kesiswaan (laporan pelanggaran) atau Sarpras (laporan kerusakan) untuk menanyakan tindak lanjutnya.']);
        }

        if (! $this->trackingProofMatchesReport($request, $report)) {
            /*
             * Simpan dulu tulisan pelapor sebelum memulangkannya ke formulir.
             *
             * Inilah penyebab penolakan yang paling sering terjadi, dan
             * korbannya adalah orang yang justru paling banyak menulis: batas
             * textarea-nya 3000 karakter, dan sebelumnya SELURUH tulisan itu
             * hilang tanpa sisa hanya karena bukti pelacakan 15 menit hangus
             * saat tombol Kirim ditekan. Draf ditaruh di session (bukan flash
             * biasa) karena harus melewati dua permintaan: pencarian ulang,
             * baru kemudian halaman hasil.
             */
            $this->rememberNoteDraft($request, $report);

            // Penanda dipasang di KEDUA field karena pemulihannya memang
            // menuntut pelapor mengisi ulang keduanya, bukan salah satu.
            $expired = 'Sesi pelacakan sudah berakhir setelah 15 menit. Masukkan lagi nomor laporan dan kode akses — tulisan Anda kami simpan dan akan muncul kembali di formulirnya.';

            return redirect()
                ->route('track.form')
                ->withErrors(['report_number' => $expired, 'access_code' => $expired]);
        }

        return null;
    }

    /**
     * Cookie bukti pelacakan, berlaku 15 menit sejak permintaan terakhir.
     *
     * Diterbitkan ulang pada setiap kunjungan halaman hasil supaya jendelanya
     * bergeser selama pelapor masih aktif. Sebelumnya masa berlakunya dihitung
     * sekali dari waktu pencarian, sehingga pelapor yang membaca riwayat
     * laporannya lebih dari 15 menit lalu menekan Kirim ditolak justru saat
     * sedang memakai halamannya.
     */
    private function trackingProofCookie(Request $request, Report $report): \Symfony\Component\HttpFoundation\Cookie
    {
        return cookie(
            'laporin_tracking_proof',
            $report->id.'|'.$this->trackingProofHash($report),
            15,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );
    }

    private function trackingProofHash(Report $report): string
    {
        return hash_hmac('sha256', (string) $report->access_code_hash, config('app.key'));
    }

    /**
     * Laporan yang ditunjuk cookie bukti pelacakan, atau null bila buktinya
     * tidak ada, rusak, atau tidak cocok dengan laporannya.
     */
    private function reportFromTrackingProof(Request $request): ?Report
    {
        $proof = $request->cookie('laporin_tracking_proof');
        if (! is_string($proof) || ! str_contains($proof, '|')) {
            return null;
        }

        [$reportId, $proofHash] = explode('|', $proof, 2);
        if (! ctype_digit($reportId) || preg_match('/^[a-f0-9]{64}$/', $proofHash) !== 1) {
            return null;
        }

        $report = Report::query()->find((int) $reportId);
        if (! $report || ! hash_equals($this->trackingProofHash($report), $proofHash)) {
            return null;
        }

        return $report;
    }

    private function noteDraftKey(Report $report): string
    {
        return 'tracking_note_draft.'.$report->id;
    }

    private function rememberNoteDraft(Request $request, Report $report): void
    {
        $note = $request->input('note');
        if (! is_string($note) || trim($note) === '') {
            return;
        }

        session()->put($this->noteDraftKey($report), mb_substr($note, 0, 3000));
    }

    /**
     * Ambil draf catatan sekali pakai. Dihapus setelah dibaca supaya tulisan
     * lama tidak muncul lagi di kunjungan berikutnya.
     */
    private function pullNoteDraft(Report $report): string
    {
        $draft = session()->pull($this->noteDraftKey($report));

        return is_string($draft) ? $draft : '';
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
