<?php

namespace App\Services\Role\Kesiswaan;

use App\Models\Report;
use App\Models\Student;
use App\Models\ViolationType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KesiswaanService
{
    public function __construct(private KesiswaanProcessor $processor)
    {
    }

    public function index(): View
    {
        $query = Report::where('report_type', 'violation');

        // Search across report_number, title, and description
        if ($search = request('search')) {
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
        if ($from_date = request('from_date')) {
            $query->whereDate('created_at', '>=', $from_date);
        }

        if ($to_date = request('to_date')) {
            $query->whereDate('created_at', '<=', $to_date);
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
            $this->processor->process($request, $report);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('status', 'Pelanggaran diproses dan poin siswa dikurangi otomatis.');
    }

    public function reject(Request $request, Report $report): RedirectResponse
    {
        try {
            $this->processor->reject($request, $report);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back();
    }

    public function complete(Request $request, Report $report): RedirectResponse
    {
        try {
            $this->processor->complete($request, $report);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('status', 'Penanganan selesai dan laporan menunggu konfirmasi pelapor.');
    }
}
