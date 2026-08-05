<?php

namespace App\Http\Controllers;

use App\Models\BullyingDetail;
use App\Models\DamageCategory;
use App\Models\DamageDetail;
use App\Models\Location;
use App\Models\QrCode;
use App\Http\Requests\PublicReportRequest;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportStatusHistory;
use App\Models\SchoolClass;
use App\Models\StaffUnit;
use App\Models\Subject;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicReportController extends Controller
{
    private const CLASS_MAJOR_LABELS = [
        'RPL' => 'Rekayasa Perangkat Lunak',
        'TKR' => 'Teknik Kendaraan Ringan',
        'TITL' => 'Teknik Instalasi Tenaga Listrik',
        'TAV' => 'Teknik Elektronika Audio Video',
    ];

    private const REPORT_NUMBER_PREFIX = 'LAP';
    private const REPORT_NUMBER_SEGMENT_LENGTH = 6;
    private const REPORT_NUMBER_RETRY_LIMIT = 10;
    private const REPORT_NUMBER_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function create(?string $qr = null): View
    {
        $qrCode = $qr ? QrCode::where('qr_identifier', $qr)->where('is_active', true)->firstOrFail() : null;
        if ($qrCode) {
            $qrCode->increment('scan_count');
        }

        // Anti-duplikat: buat token baru setiap form dibuka
        $submitToken = (string) Str::uuid();
        session(['report_submit_token' => $submitToken]);

        session(['math_captcha_answer' => ($a = random_int(1, 9)) + ($b = random_int(1, 9))]);

        $majorOrder = array_flip(array_keys(self::CLASS_MAJOR_LABELS));
        $classes = SchoolClass::where('is_active', true)->get()->sort(function (SchoolClass $left, SchoolClass $right) use ($majorOrder): int {
            $leftMajor = strtoupper(trim((string) ($left->major ?: 'LAINNYA')));
            $rightMajor = strtoupper(trim((string) ($right->major ?: 'LAINNYA')));
            $leftRank = $majorOrder[$leftMajor] ?? PHP_INT_MAX;
            $rightRank = $majorOrder[$rightMajor] ?? PHP_INT_MAX;

            return ($leftRank <=> $rightRank)
                ?: strnatcasecmp($leftMajor, $rightMajor)
                ?: strnatcasecmp((string) $left->grade_level, (string) $right->grade_level)
                ?: strnatcasecmp($left->class_name, $right->class_name);
        });

        $classesByMajor = $classes->groupBy(
            fn (SchoolClass $class): string => strtoupper(trim((string) ($class->major ?: 'LAINNYA')))
        );

        return view('public.report-form', [
            'qrCode' => $qrCode,
            'captchaQuestion' => "$a + $b",
            'classesByMajor' => $classesByMajor,
            'classMajorLabels' => self::CLASS_MAJOR_LABELS,
            'subjects' => Subject::where('is_active', true)->orderBy('subject_name')->get(),
            'staffUnits' => StaffUnit::where('is_active', true)->orderBy('unit_name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('location_name')->get(),
            'damageCategories' => DamageCategory::where('is_active', true)->orderBy('category_name')->get(),
        ]);
    }

    public function store(PublicReportRequest $request): RedirectResponse
    {
        $sessionToken = session('report_submit_token');
        $submittedToken = (string) $request->input('report_submit_token', '');
        if ($submittedToken !== '' && (! $sessionToken || ! hash_equals((string) $sessionToken, $submittedToken))) {
            throw ValidationException::withMessages([
                'form' => 'Sesi formulir sudah habis atau Anda sudah mengirim laporan sebelumnya. Silakan buka halaman baru untuk membuat laporan.',
            ]);
        }

        $validated = $request->validated();

        if ((int) $validated['captcha'] !== (int) session('math_captcha_answer')) {
            throw ValidationException::withMessages([
                'captcha' => 'CAPTCHA salah. Hitung ulang pertanyaan yang tampil lalu isi dengan angka yang benar.',
            ]);
        }

        [$report, $accessCode] = $this->createReportWithRandomNumber($request, $validated);

        // === ANTI-DUPLIKAT: kosongkan token SEBELUM redirect ===
        session()->forget(['math_captcha_answer', 'report_submit_token']);
        $request->session()->regenerateToken();

        return redirect()
            ->route('public.report.success', $report->public_token)
            ->with('access_code', $accessCode)
            ->with('success_report_id', $report->id);
    }

    public function success(Report $report): View|RedirectResponse
    {
        if ((int) session('success_report_id') !== $report->id) {
            return redirect()
                ->route('track.form')
                ->withErrors(['report_number' => 'Halaman sukses hanya bisa dibuka setelah laporan berhasil dikirim. Untuk melihat laporan, masukkan nomor laporan dan kode akses.']);
        }

        // Kosongkan session agar tidak bisa back ke form sukses
        session()->forget('success_report_id');

        return view('public.success', ['report' => $report, 'accessCode' => session('access_code')]);
    }

    private function createReportWithRandomNumber(Request $request, array $validated): array
    {
        for ($attempt = 0; $attempt < self::REPORT_NUMBER_RETRY_LIMIT; $attempt++) {
            try {
                return DB::transaction(function () use ($request, $validated) {
                    $accessCode = (string) random_int(100000, 999999);
                    $report = Report::create($this->reportData(
                        $request,
                        $validated,
                        $accessCode,
                        $this->generateReportNumber()
                    ));

                    if ($report->report_type === 'violation') {
                        BullyingDetail::create(['report_id' => $report->id] + collect($validated)->only([
                            'reporter_position', 'bullying_type', 'victim_name', 'victim_class_id',
                            'alleged_actor_name', 'alleged_actor_class_id', 'witness_name', 'impact_description',
                        ])->toArray());
                    } else {
                        DamageDetail::create(['report_id' => $report->id, 'priority' => $validated['priority'] ?? $validated['urgency']] + collect($validated)->only([
                            'item_name', 'item_category', 'damage_condition', 'suspected_cause',
                        ])->toArray());
                    }

                    foreach ($request->file('attachments', []) as $file) {
                        $path = $file->store('report-attachments/'.$report->id, 'private');
                        ReportAttachment::create([
                            'report_id' => $report->id,
                            'uploader_type' => 'reporter',
                            'original_name' => $this->safeOriginalName($file->getClientOriginalName()),
                            'stored_name' => basename($path),
                            'file_path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(),
                            'attachment_type' => 'initial_evidence',
                        ]);
                    }

                    $this->history($report, null, $report->status, 'Laporan diterima sistem.');

                    // === KIRIM EMAIL NOTIFIKASI (log driver) ===
                    $this->kirimNotifikasiEmail($report, $accessCode);

                    return [$report, $accessCode];
                });
            } catch (QueryException $exception) {
                if ($this->isReportIdentifierCollision($exception)) {
                    continue;
                }

                throw $this->convertQueryExceptionToValidationException($exception);
            }
        }

        throw ValidationException::withMessages([
            'report_number' => 'Nomor laporan belum berhasil dibuat. Silakan coba kembali.',
        ]);
    }

    private function reportData(Request $request, array $validated, string $accessCode, string $reportNumber): array
    {
        return [
            'report_number' => $reportNumber,
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make($accessCode),
            'qr_code_id' => $validated['qr_code_id'] ?? null,
            'reporter_type' => $validated['reporter_type'],
            'reporter_name' => $validated['reporter_name'],
            'reporter_class_id' => $validated['reporter_class_id'] ?? null,
            'reporter_absence_number' => $validated['reporter_absence_number'] ?? null,
            'reporter_subject_id' => $validated['reporter_subject_id'] ?? null,
            'reporter_staff_unit_id' => $validated['reporter_staff_unit_id'] ?? null,
            'reporter_phone' => $validated['reporter_phone'] ?? null,
            'reporter_email' => $validated['reporter_email'] ?? null,
            'report_type' => $validated['report_type'],
            'title' => $validated['title'],
            'related_class_id' => $validated['related_class_id'] ?? null,
            'location_id' => $validated['location_id'] ?? null,
            'custom_location' => $validated['custom_location'] ?? null,
            'incident_date' => $validated['incident_date'] ?? now()->toDateString(),
            'incident_time' => $validated['incident_time'] ?? null,
            'description' => $validated['description'],
            'urgency' => $validated['urgency'],
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => $validated['report_type'] === 'violation' ? 'kesiswaan' : 'sarpras',
            'consent_accepted_at' => now(),
            'submitted_ip_hash' => hash_hmac('sha256', $this->clientIpForAudit($request), config('app.key')),
            'submitted_user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ];
    }

    private function kirimNotifikasiEmail(Report $report, string $accessCode): void
    {
        $email = $report->reporter_email;
        if (! $email) {
            return;
        }

        try {
            // Menggunakan log driver: email masuk ke storage/logs/mail.log
            Mail::mailer('log')->send(
                'emails.laporan-diterima',
                [
                    'report' => $report,
                    'accessCode' => $accessCode,
                    'reportNumber' => $report->report_number,
                    'reportTypeLabel' => $report->report_type === 'violation' ? 'Perundungan / Pelanggaran' : 'Kerusakan Fasilitas',
                    'statusLabel' => 'Menunggu Verifikasi',
                ],
                function ($message) use ($email, $report) {
                    $message->to($email)
                        ->subject("[LAPORIN] Laporan {$report->report_number} berhasil diterima")
                        ->from(config('mail.from.address', 'noreply@laporin.sch.id'), config('mail.from.name', 'LAPORIN'));
                }
            );
        } catch (\Throwable $e) {
            // Jangan fail-kan submission kalau email gagal
            Log::error('Gagal kirim email notifikasi laporan: '.$e->getMessage(), [
                'report_id' => $report->id,
                'email' => $email,
            ]);
        }
    }

    public function kirimNotifikasiStatus(Report $report, string $statusLabel, ?string $catatan = null): void
    {
        $email = $report->reporter_email;
        if (! $email) {
            return;
        }

        try {
            Mail::mailer('log')->send(
                'emails.status-perubahan',
                [
                    'report' => $report,
                    'statusLabel' => $statusLabel,
                    'catatan' => $catatan,
                    'reportNumber' => $report->report_number,
                    'reportTypeLabel' => $report->report_type === 'violation' ? 'Perundungan / Pelanggaran' : 'Kerusakan Fasilitas',
                ],
                function ($message) use ($email, $report, $statusLabel) {
                    $message->to($email)
                        ->subject("[LAPORIN] Laporan {$report->report_number}: status berubah menjadi {$statusLabel}")
                        ->from(config('mail.from.address', 'noreply@laporin.sch.id'), config('mail.from.name', 'LAPORIN'));
                }
            );
        } catch (\Throwable $e) {
            Log::error('Gagal kirim email notifikasi status: '.$e->getMessage(), [
                'report_id' => $report->id,
                'email' => $email,
            ]);
        }
    }

    private function clientIpForAudit(Request $request): string
    {
        $cfConnectingIp = $request->headers->get('CF-Connecting-IP');
        if (is_string($cfConnectingIp) && filter_var($cfConnectingIp, FILTER_VALIDATE_IP)) {
            return $cfConnectingIp;
        }

        $forwardedFor = $request->headers->get('X-Forwarded-For');
        if (is_string($forwardedFor)) {
            $firstForwardedIp = trim(explode(',', $forwardedFor)[0] ?? '');
            if (filter_var($firstForwardedIp, FILTER_VALIDATE_IP)) {
                return $firstForwardedIp;
            }
        }

        return $request->ip() ?? 'unknown';
    }

    private function generateReportNumber(): string
    {
        return self::REPORT_NUMBER_PREFIX.'-'.implode('-', [
            $this->randomReportSegment(),
            $this->randomReportSegment(),
        ]);
    }

    private function randomReportSegment(): string
    {
        $segment = '';
        $alphabetLength = strlen(self::REPORT_NUMBER_ALPHABET);

        for ($i = 0; $i < self::REPORT_NUMBER_SEGMENT_LENGTH; $i++) {
            $segment .= self::REPORT_NUMBER_ALPHABET[random_int(0, $alphabetLength - 1)];
        }

        return $segment;
    }

    private function isReportIdentifierCollision(QueryException $exception): bool
    {
        if (! $this->isDuplicateConstraintError($exception)) {
            return false;
        }

        $message = $exception->getMessage();

        return preg_match('/(?:reports[_\.](?:report_number|public_token)|report_number|public_token)/i', $message) > 0;
    }

    private function isDuplicateConstraintError(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = $exception->getMessage();

        return $sqlState === '23505'
            || $driverCode === 1062
            || (str_contains($message, 'UNIQUE constraint failed') && str_contains($message, 'reports.'))
            || (str_contains($message, 'duplicate key value violates unique constraint'));
    }

    private function convertQueryExceptionToValidationException(QueryException $exception): ValidationException
    {
        if ($this->isForeignKeyViolation($exception)) {
            return ValidationException::withMessages([
                'report_number' => 'Pilihan kelas, lokasi, kategori, atau unit tidak lagi tersedia. Muat ulang halaman dan pilih kembali.',
            ]);
        }

        if ($this->isDeadlockOrTimeout($exception) || $this->isConnectionIssue($exception)) {
            return ValidationException::withMessages([
                'report_number' => 'Sistem sedang mengalami gangguan koneksi. Silakan coba kembali beberapa saat lagi.',
            ]);
        }

        return ValidationException::withMessages([
            'report_number' => 'Laporan belum dapat dikirim karena terjadi kesalahan sistem.',
        ]);
    }

    private function isForeignKeyViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = strtolower($exception->getMessage());

        return $sqlState === '23000' && in_array($driverCode, [1452, 1451, 19], true)
            || str_contains($message, 'foreign key constraint')
            || str_contains($message, 'foreign key')
            || str_contains($message, 'constraint failed');
    }

    private function isDeadlockOrTimeout(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = strtolower($exception->getMessage());

        return in_array($driverCode, [1213, 1205, 1206], true)
            || $sqlState === '40001'
            || str_contains($message, 'deadlock')
            || str_contains($message, 'lock wait timeout')
            || str_contains($message, 'database is locked');
    }

    private function isConnectionIssue(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $message = strtolower($exception->getMessage());

        return $sqlState === '08006'
            || $sqlState === '08001'
            || str_contains($message, 'could not find driver')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'server has gone away')
            || str_contains($message, 'no connection could be made');
    }

    private function safeOriginalName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'attachment';

        return Str::limit($name, 120, '');
    }

    private function history(Report $report, ?string $old, string $new, ?string $note = null): void
    {
        ReportStatusHistory::create([
            'report_id' => $report->id,
            'actor_type' => 'reporter',
            'previous_status' => $old,
            'new_status' => $new,
            'public_note' => $note,
        ]);
    }
}
