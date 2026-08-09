<?php

namespace App\Services\PublicReport;

use App\Models\BullyingDetail;
use App\Models\DamageDetail;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportStatusHistory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PublicReportService
{
    private const REPORT_NUMBER_PREFIX = 'LAP';
    private const REPORT_NUMBER_SEGMENT_LENGTH = 6;
    private const REPORT_NUMBER_RETRY_LIMIT = 10;
    private const REPORT_NUMBER_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function create(Request $request, array $validated): array
{
    for ($attempt = 0; $attempt < self::REPORT_NUMBER_RETRY_LIMIT; $attempt++) {
        $storedPaths = [];

        try {
            [$report, $accessCode] = DB::transaction(function () use ($request, $validated, &$storedPaths) {
                $accessCode = (string) random_int(100000, 999999);

                $report = Report::create(
                    $this->reportData(
                        $request,
                        $validated,
                        $accessCode,
                        $this->generateReportNumber()
                    )
                );

                Log::info('Public report created', [
                    'report_id' => $report->id,
                    'report_number' => $report->report_number,
                    'report_type' => $report->report_type,
                    'reporter_type' => $report->reporter_type,
                    'ip_hash' => $report->submitted_ip_hash,
                    'timestamp' => now()->toIso8601String(),
                ]);

                if ($report->report_type === 'violation') {
                    BullyingDetail::create([
                        'report_id' => $report->id,
                    ] + collect($validated)->only([
                        'reporter_position',
                        'bullying_type',
                        'victim_name',
                        'victim_class_id',
                        'alleged_actor_name',
                        'alleged_actor_class_id',
                        'witness_name',
                        'impact_description',
                    ])->toArray());
                } else {
                    DamageDetail::create([
                        'report_id' => $report->id,
                        'priority' => null,
                    ] + collect($validated)->only([
                        'item_name',
                        'item_category',
                        'damage_condition',
                        'suspected_cause',
                    ])->toArray());
                }

                foreach ($request->file('attachments', []) as $file) {
                    $path = $file->store(
                        'report-attachments/'.$report->id,
                        'private'
                    );

                    /*
                     * Filesystem tidak mengikuti rollback database.
                     */
                    $storedPaths[] = $path;

                    ReportAttachment::create([
                        'report_id' => $report->id,
                        'uploader_type' => 'reporter',
                        'original_name' => $this->safeOriginalName(
                            $file->getClientOriginalName()
                        ),
                        'stored_name' => basename($path),
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'attachment_type' => 'initial_evidence',
                    ]);

                    Log::debug(
                        'File attachment stored for public report',
                        [
                            'report_id' => $report->id,
                            'mime_type' => $file->getMimeType(),
                            'size' => $file->getSize(),
                        ]
                    );
                }

                $this->history(
                    $report,
                    null,
                    $report->status,
                    'Laporan diterima sistem.'
                );

                return [$report, $accessCode];
            });

            // PENTING:
            // Email dikirim SETELAH transaction database berhasil COMMIT.
            // Kegagalan SMTP tidak membatalkan laporan yang sudah tersimpan.
            $notificationSent = $this->kirimNotifikasiEmail(
                $report,
                $accessCode
            );

            return [
                $report,
                $accessCode,
                $notificationSent,
            ];
        } catch (QueryException $exception) {
            foreach ($storedPaths as $storedPath) {
                try {
                    Storage::disk('private')->delete(
                        $storedPath
                    );
                } catch (Throwable) {
                    // Jangan menutupi exception database asli.
                }
            }

            if ($this->isReportIdentifierCollision($exception)) {
                continue;
            }

            throw $this->convertQueryExceptionToValidationException(
                $exception
            );
        } catch (Throwable $exception) {
            foreach ($storedPaths as $storedPath) {
                try {
                    Storage::disk('private')->delete(
                        $storedPath
                    );
                } catch (Throwable) {
                    // Jangan menutupi exception asli.
                }
            }

            throw $exception;
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

    private function kirimNotifikasiEmail(Report $report, string $accessCode): bool
    {
        $email = $report->reporter_email;
        if (! $email) {
            return false;
        }

        try {
            Mail::send(
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

            return true;
        } catch (\Throwable $e) {
            Log::warning(
                'Gagal kirim email notifikasi laporan.',
                [
                    'report_id' => $report->id,
                    'exception' => $e::class,
                ]
            );

            return false;
        }
    }

    private function clientIpForAudit(Request $request): string
{
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
