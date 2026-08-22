<?php

namespace App\Services\Role\Kesiswaan;

use App\Models\Report;
use App\Models\Student;
use App\Models\ViolationType;
use App\Support\RequestFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KesiswaanService
{
    public function __construct(private KesiswaanProcessor $processor)
    {
    }

    public function index(): View
    {
        $query = Report::where('report_type', 'violation')
            ->with(['bullyingDetail.allegedActorClass', 'relatedClass', 'attachments']);

        // Search across report_number, title, and description
        if ($search = RequestFilters::searchTerm(request('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('report_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status - validate against allowed statuses
        $allowedStatuses = ['menunggu_verifikasi', 'memerlukan_informasi', 'dibuka_kembali', 'sedang_ditangani', 'menunggu_konfirmasi', 'selesai', 'ditolak'];
        if ($status = request('status')) {
            if (in_array($status, $allowedStatuses, true)) {
                $query->where('status', $status);
            }
        }

        // Filter by date range
        if ($fromDate = RequestFilters::isoDate(request('from_date'))) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate = RequestFilters::isoDate(request('to_date'))) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return view('kesiswaan.index', [
            'reports' => $query->latest()->paginate(15),
            'students' => Student::with('class')->orderBy('name')->get(),
            'types' => ViolationType::where('is_active', true)->get(),
        ]);
    }

    public function process(Request $request, Report $report): RedirectResponse
    {
        try {
            $pointsDeducted = $this->processor->process($request, $report);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // Pesan dibedakan karena laporan yang dibuka kembali memang tidak memotong
        // poin lagi; pesan tunggal membuat operator ragu apakah poin dobel.
        return back()->with('status', $pointsDeducted
            ? 'Pelanggaran diproses dan poin siswa dikurangi otomatis.'
            : 'Laporan ditindaklanjuti kembali. Poin siswa tidak dipotong ulang karena pelanggaran ini sudah tercatat sebelumnya.');
    }

    public function reject(Request $request, Report $report): RedirectResponse
    {
        try {
            $this->processor->reject($request, $report);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        // Tanpa flash status, tombol "Tolak" yang berhasil terlihat sama seperti
        // tombol yang tidak berfungsi karena halaman kembali tanpa umpan balik.
        return back()->with('status', 'Laporan ditolak dan alur pemrosesannya dihentikan.');
    }

    public function complete(Request $request, Report $report): RedirectResponse
    {
        try {
            $this->processor->complete($request, $report);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('status', 'Penanganan selesai dan laporan menunggu konfirmasi pelapor.');
    }
}
