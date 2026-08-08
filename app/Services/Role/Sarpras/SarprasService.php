<?php

namespace App\Services\Role\Sarpras;

use App\Models\Report;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SarprasService
{
    public function __construct(private SarprasProcessor $processor)
    {
    }

    public function index(): View
    {
        $query = Report::where('report_type', 'damage')->with('damageDetail');

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

        // Filter by priority from damage detail
        $allowedPriorities = ['rendah', 'sedang', 'tinggi', 'darurat'];
        if ($priority = request('priority')) {
            if (in_array($priority, $allowedPriorities, true)) {
                $query->whereHas('damageDetail', function ($q) use ($priority) {
                    $q->where('priority', $priority);
                });
            }
        }

        // Filter by date range
        if ($from_date = request('from_date')) {
            $query->whereDate('created_at', '>=', $from_date);
        }

        if ($to_date = request('to_date')) {
            $query->whereDate('created_at', '<=', $to_date);
        }

        return view('sarpras.index', [
            'reports' => $query->latest()->paginate(15),
        ]);
    }

    public function process(Request $request, Report $report): RedirectResponse
    {
        try {
            $this->processor->process($request, $report);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('status', 'Laporan kerusakan diproses.');
    }

    public function reject(Request $request, Report $report): RedirectResponse
    {
        try {
            $this->processor->reject($request, $report);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('status', 'Laporan kerusakan ditolak.');
    }
}
