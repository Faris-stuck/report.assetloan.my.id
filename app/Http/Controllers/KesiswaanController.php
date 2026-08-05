<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportStatusHistory;
use App\Models\Student;
use App\Models\StudentViolation;
use App\Models\ViolationType;
use App\Traits\ReportNotificationTrait;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KesiswaanController extends Controller
{
    use ReportNotificationTrait;
    private const PROCESSABLE_STATUSES = ['menunggu_verifikasi', 'memerlukan_informasi', 'dibuka_kembali'];

    public function index(): View
    {
        return view('kesiswaan.index', [
            'reports' => Report::where('report_type', 'violation')->latest()->paginate(15),
            'students' => Student::with('class')->orderBy('name')->get(),
            'types' => ViolationType::where('is_active', true)->get(),
        ]);
    }

    public function process(Request $request, Report $report): RedirectResponse
    {
        if ($report->report_type !== 'violation') {
            return back()->withErrors(['report' => 'Menu Kesiswaan hanya dapat memproses laporan pelanggaran siswa.'])->withInput();
        }

        if (! in_array($report->status, self::PROCESSABLE_STATUSES, true)) {
            return back()->withErrors(['report' => 'Laporan ini sudah pernah diproses atau tidak bisa diproses ulang.'])->withInput();
        }

        if (StudentViolation::where('report_id', $report->id)->exists() && ! in_array($report->status, ['dibuka_kembali', 'memerlukan_informasi'], true)) {
            return back()->withErrors(['report' => 'Poin untuk laporan ini sudah pernah diproses.'])->withInput();
        }

        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'violation_type_id' => ['required', Rule::exists('violation_types', 'id')->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $report, $data): void {
            $lockedReport = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($lockedReport->report_type !== 'violation') {
                throw ValidationException::withMessages(['report' => 'Menu Kesiswaan hanya dapat memproses laporan pelanggaran siswa.']);
            }
            if (! in_array($lockedReport->status, self::PROCESSABLE_STATUSES, true)) {
                throw ValidationException::withMessages(['report' => 'Laporan ini sudah pernah diproses atau tidak bisa diproses ulang.']);
            }
            $existingViolation = StudentViolation::where('report_id', $lockedReport->id)->exists();

            $student = Student::lockForUpdate()->findOrFail($data['student_id']);
            $type = ViolationType::where('is_active', true)->findOrFail($data['violation_type_id']);

            if (! $existingViolation) {
                // Logika krusial: poin siswa berawal 100 dan dikurangi otomatis sesuai bobot pelanggaran, tidak boleh minus.
                $student->update(['point' => max(0, $student->point - $type->point_reduction)]);

                StudentViolation::create([
                    'student_id' => $student->id,
                    'report_id' => $lockedReport->id,
                    'violation_type_id' => $type->id,
                    'point_reduced' => $type->point_reduction,
                    'note' => $data['note'] ?? null,
                    'processed_by_user_id' => $request->user()->id,
                ]);
            }

            $old = $lockedReport->status;
            $lockedReport->update([
                'status' => 'sedang_ditangani',
                'violation_type_id' => $type->id,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'assigned_to_role' => 'kesiswaan',
            ]);

            $publicNote = $existingViolation
                ? 'Laporan pelanggaran ditindaklanjuti kembali tanpa memotong poin dua kali.'
                : 'Laporan pelanggaran diverifikasi dan sedang ditangani.';

            ReportStatusHistory::create([
                'report_id' => $lockedReport->id,
                'changed_by_user_id' => $request->user()->id,
                'actor_type' => 'kesiswaan',
                'previous_status' => $old,
                'new_status' => 'sedang_ditangani',
                'public_note' => $publicNote,
                'internal_note' => $data['note'] ?? null,
            ]);

            $this->kirimNotifikasiStatus($lockedReport, $this->statusLabel('sedang_ditangani'), $publicNote);
        });

        return back()->with('status', 'Pelanggaran diproses dan poin siswa dikurangi otomatis.');
    }

    public function reject(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);
        if ($report->report_type !== 'violation') {
            return back()->withErrors(['report' => 'Menu Kesiswaan hanya dapat menolak laporan pelanggaran siswa.'])->withInput();
        }
        if (! in_array($report->status, self::PROCESSABLE_STATUSES, true)) {
            return back()->withErrors(['report' => 'Laporan ini tidak bisa ditolak pada status saat ini.'])->withInput();
        }

        $old = $report->status;
        $report->update(['status' => 'ditolak', 'rejection_reason' => $data['reason']]);
        ReportStatusHistory::create([
            'report_id' => $report->id,
            'changed_by_user_id' => $request->user()->id,
            'actor_type' => 'kesiswaan',
            'previous_status' => $old,
            'new_status' => 'ditolak',
            'public_note' => 'Laporan ditolak.',
            'internal_note' => $data['reason'],
        ]);

        $this->kirimNotifikasiStatus($report, $this->statusLabel('ditolak'), 'Laporan ditolak.');

        return back();
    }

    public function complete(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $report, $data): void {
            $lockedReport = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($lockedReport->report_type !== 'violation') {
                throw ValidationException::withMessages([
                    'report' => 'Kesiswaan hanya dapat menyelesaikan penanganan laporan perundungan atau pelanggaran.',
                ]);
            }

            if ($lockedReport->status !== 'sedang_ditangani') {
                throw ValidationException::withMessages([
                    'report' => 'Laporan harus berstatus sedang ditangani sebelum dikirim ke konfirmasi pelapor.',
                ]);
            }

            $lockedReport->update(['status' => 'menunggu_konfirmasi']);
            $publicNote = 'Kesiswaan menyelesaikan tindak lanjut dan meminta konfirmasi pelapor.';
            ReportStatusHistory::create([
                'report_id' => $lockedReport->id,
                'changed_by_user_id' => $request->user()->id,
                'actor_type' => 'kesiswaan',
                'previous_status' => 'sedang_ditangani',
                'new_status' => 'menunggu_konfirmasi',
                'public_note' => $publicNote,
                'internal_note' => $data['note'] ?? null,
            ]);

            $this->kirimNotifikasiStatus($lockedReport, $this->statusLabel('menunggu_konfirmasi'), $publicNote);
        });

        return back()->with('status', 'Penanganan selesai dan laporan menunggu konfirmasi pelapor.');
    }
}
