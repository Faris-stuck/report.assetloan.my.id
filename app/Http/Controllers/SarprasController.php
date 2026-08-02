<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\ReportAttachment;
use App\Models\ReportStatusHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SarprasController extends Controller
{
    private const PROCESSABLE_STATUSES = ['menunggu_verifikasi', 'memerlukan_informasi', 'dibuka_kembali', 'sedang_ditangani'];

    public function index(): View
    {
        return view('sarpras.index', [
            'reports' => Report::where('report_type', 'damage')->with('damageDetail')->latest()->paginate(15),
        ]);
    }

    public function process(Request $request, Report $report): RedirectResponse
    {
        if ($report->report_type !== 'damage') {
            return back()->withErrors(['report' => 'Menu Sarpras hanya dapat memproses laporan kerusakan fasilitas.'])->withInput();
        }

        if (! in_array($report->status, self::PROCESSABLE_STATUSES, true)) {
            return back()->withErrors(['report' => 'Laporan ini sudah selesai/ditolak dan tidak bisa diproses ulang.'])->withInput();
        }

        $data = $request->validate([
            'priority' => ['required', 'in:rendah,sedang,tinggi,darurat'],
            'scheduled_repair_at' => ['nullable', 'date', 'after_or_equal:now'],
            'note' => ['nullable', 'string', 'max:2000'],
            'repair_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
        ]);

        DB::transaction(function () use ($request, $report, $data): void {
            $lockedReport = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($lockedReport->report_type !== 'damage') {
                throw ValidationException::withMessages(['report' => 'Menu Sarpras hanya dapat memproses laporan kerusakan fasilitas.']);
            }
            if (! in_array($lockedReport->status, self::PROCESSABLE_STATUSES, true)) {
                throw ValidationException::withMessages(['report' => 'Laporan ini sudah selesai/ditolak dan tidak bisa diproses ulang.']);
            }

            $old = $lockedReport->status;
            $detail = $lockedReport->damageDetail;
            if (! $detail) {
                throw ValidationException::withMessages(['report' => 'Detail kerusakan tidak lengkap. Minta pelapor melengkapi laporan terlebih dahulu.']);
            }

            $done = $request->hasFile('repair_photo');
            $detail->update([
                'priority' => $data['priority'],
                'scheduled_repair_at' => $data['scheduled_repair_at'] ?? null,
                'repaired_at' => $done ? now() : null,
            ]);

            if ($done) {
                $file = $request->file('repair_photo');
                $path = $file->store('report-attachments/'.$lockedReport->id, 'private');
                ReportAttachment::create([
                    'report_id' => $lockedReport->id,
                    'uploaded_by_user_id' => $request->user()->id,
                    'uploader_type' => 'sarpras',
                    'original_name' => $this->safeOriginalName($file->getClientOriginalName()),
                    'stored_name' => basename($path),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'attachment_type' => 'repair_after',
                ]);
            }

            $new = $done ? 'selesai' : 'sedang_ditangani';
            $lockedReport->update([
                'status' => $new,
                'verified_by' => $request->user()->id,
                'verified_at' => now(),
                'assigned_to_role' => 'sarpras',
            ]);

            ReportStatusHistory::create([
                'report_id' => $lockedReport->id,
                'changed_by_user_id' => $request->user()->id,
                'actor_type' => 'sarpras',
                'previous_status' => $old,
                'new_status' => $new,
                'public_note' => $done ? 'Perbaikan selesai.' : 'Perbaikan dijadwalkan.',
                'internal_note' => $data['note'] ?? null,
            ]);
        });

        return back()->with('status', 'Laporan kerusakan diproses.');
    }

    public function reject(Request $request, Report $report): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $report, $data): void {
            $lockedReport = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();

            if ($lockedReport->report_type !== 'damage') {
                throw ValidationException::withMessages([
                    'report' => 'Sarpras hanya dapat menolak laporan kerusakan fasilitas.',
                ]);
            }

            if (! in_array($lockedReport->status, self::PROCESSABLE_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'report' => 'Laporan ini tidak bisa ditolak pada status saat ini.',
                ]);
            }

            $old = $lockedReport->status;
            $lockedReport->update([
                'status' => 'ditolak',
                'rejection_reason' => $data['reason'],
            ]);
            ReportStatusHistory::create([
                'report_id' => $lockedReport->id,
                'changed_by_user_id' => $request->user()->id,
                'actor_type' => 'sarpras',
                'previous_status' => $old,
                'new_status' => 'ditolak',
                'public_note' => 'Laporan kerusakan ditolak oleh Sarpras.',
                'internal_note' => $data['reason'],
            ]);
        });

        return back()->with('status', 'Laporan kerusakan ditolak.');
    }

    private function safeOriginalName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'attachment';

        return Str::limit($name, 120, '');
    }
}
