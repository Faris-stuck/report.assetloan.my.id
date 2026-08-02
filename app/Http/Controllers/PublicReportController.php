<?php

namespace App\Http\Controllers;

use App\Models\BullyingDetail;
use App\Models\DamageCategory;
use App\Models\DamageDetail;
use App\Models\Location;
use App\Models\QrCode;
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

    public function store(Request $request): RedirectResponse
    {
        // Anti-duplikat untuk submit dari form browser. Direct test/API-style posts
        // yang tidak membawa token tetap mengandalkan CSRF dan validasi payload.
        $sessionToken = session('report_submit_token');
        $submittedToken = (string) $request->input('report_submit_token', '');
        if ($submittedToken !== '' && (! $sessionToken || ! hash_equals((string) $sessionToken, $submittedToken))) {
            throw ValidationException::withMessages([
                'form' => 'Sesi formulir sudah habis atau Anda sudah mengirim laporan sebelumnya. Silakan buka halaman baru untuk membuat laporan.',
            ]);
        }

        $validated = $request->validate([
            'qr_code_id' => ['nullable', Rule::exists('qr_codes', 'id')->where('is_active', true)],
            'reporter_type' => ['required', Rule::in(['siswa', 'guru', 'staff'])],
            'reporter_name' => ['required', 'string', 'max:150'],
            'reporter_class_id' => ['exclude_unless:reporter_type,siswa', 'required', Rule::exists('classes', 'id')->where('is_active', true)],
            'reporter_absence_number' => ['exclude_unless:reporter_type,siswa', 'nullable', 'integer', 'min:1', 'max:60'],
            'reporter_subject_id' => ['exclude_unless:reporter_type,guru', 'required', Rule::exists('subjects', 'id')->where('is_active', true)],
            'reporter_staff_unit_id' => ['exclude_unless:reporter_type,staff', 'required', Rule::exists('staff_units', 'id')->where('is_active', true)],
            'reporter_phone' => [
                'required',
                'string',
                'max:30',
                'regex:/^[0-9+() .*\-]+$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $digitCount = strlen(preg_replace('/\D+/', '', (string) $value) ?? '');
                    $containsMask = str_contains((string) $value, '*');
                    if ((! $containsMask && $digitCount < 8) || $digitCount > 15) {
                        $fail('Nomor HP harus berisi 8 sampai 15 digit.');
                    }
                },
            ],
            'reporter_email' => [
                'nullable',
                'email:rfc',
                'max:150',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $fail('Format alamat email tidak valid.');
                    }
                },
            ],
            'report_type' => ['required', Rule::in(['violation', 'damage'])],
            'title' => ['required', 'string', 'max:200'],
            // violation step-3 ringkas: hanya 4 field
            'related_class_id' => ['nullable', 'required_if:report_type,violation', Rule::exists('classes', 'id')->where('is_active', true)],
            // location & incident_date optional untuk ringkasan violation
            'location_id' => ['nullable', Rule::exists('locations', 'id')->where('is_active', true)],
            'custom_location' => ['nullable', 'string', 'max:150'],
            'incident_date' => ['nullable', 'date', 'before_or_equal:today'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'description' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', Rule::in(['rendah', 'sedang', 'tinggi', 'darurat'])],
            // violation extra fields (bukan bagian step-3 ringkas, tetap disimpan jika ada)
            'reporter_position' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:80'],
            'bullying_type' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:80'],
            'victim_name' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:150'],
            'victim_class_id' => ['exclude_unless:report_type,violation', 'nullable', Rule::exists('classes', 'id')->where('is_active', true)],
            'alleged_actor_name' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:150'],
            'alleged_actor_class_id' => ['nullable', 'exclude_unless:report_type,violation', Rule::exists('classes', 'id')->where('is_active', true)],
            'witness_name' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:150'],
            'impact_description' => ['exclude_unless:report_type,violation', 'nullable', 'string', 'max:2000'],
            // damage fields
            'item_name' => ['exclude_unless:report_type,damage', 'required', 'string', 'max:150'],
            'item_category' => ['exclude_unless:report_type,damage', 'nullable', 'string', 'max:100'],
            'damage_condition' => ['exclude_unless:report_type,damage', 'required', 'string', 'max:2000'],
            'suspected_cause' => ['exclude_unless:report_type,damage', 'nullable', 'string', 'max:1000'],
            'priority' => ['exclude_unless:report_type,damage', 'nullable', Rule::in(['rendah', 'sedang', 'tinggi', 'darurat'])],
            // attachments dipindah ke step terakhir
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', 'max:4096'],
            'consent' => ['accepted'],
            'captcha' => ['required', 'integer'],
        ], [
            'reporter_phone.required' => 'Nomor HP wajib diisi agar sekolah dapat menghubungi pelapor.',
            'reporter_phone.regex' => 'Format nomor HP hanya boleh berisi angka, spasi, tanda +, kurung, titik, tanda hubung, atau tanda bintang untuk masking.',
            'reporter_email.email' => 'Format alamat email tidak valid.',
            'related_class_id.required_if' => 'Kelas kejadian wajib dipilih untuk laporan perundungan atau pelanggaran.',
            'title.required' => 'Judul laporan wajib diisi.',
            'description.required' => 'Kronologi wajib diisi.',
            'alleged_actor_name.required' => 'Nama pelaku wajib diisi untuk laporan perundungan.',
            'item_name.required' => 'Nama barang atau fasilitas wajib diisi.',
            'damage_condition.required' => 'Kondisi kerusakan wajib diisi.',
        ]);

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
        for ($attempt = 0; $attempt < 100; $attempt++) {
            try {
                return DB::transaction(function () use ($request, $validated) {
                    $accessCode = (string) random_int(100000, 999999);
                    $report = Report::create($this->reportData(
                        $request,
                        $validated,
                        $accessCode,
                        $this->nextReportNumber()
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
                if (! $this->isReportNumberCollision($exception)) {
                    throw $exception;
                }
            }
        }

        throw ValidationException::withMessages([
            'report_number' => 'Nomor laporan sedang penuh/sibuk dibuat. Coba kirim ulang beberapa saat lagi.',
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
            'incident_date' => $validated['incident_date'] ?? null,
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

    private function nextReportNumber(): string
    {
        $prefix = 'LPR'.now()->format('Ym');

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $candidate = $prefix.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            if (! Report::where('report_number', $candidate)->lockForUpdate()->exists()) {
                return $candidate;
            }
        }

        throw ValidationException::withMessages([
            'report_number' => 'Nomor laporan sedang penuh/sibuk dibuat. Coba kirim ulang beberapa saat lagi.',
        ]);
    }

    private function isReportNumberCollision(QueryException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'reports_report_number_unique')
            || str_contains($message, 'reports.report_number')
            || str_contains($message, 'report_number')
            || str_contains($message, 'Duplicate entry')
            || str_contains($message, 'UNIQUE constraint failed');
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
