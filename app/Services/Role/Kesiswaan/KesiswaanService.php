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
        return view('kesiswaan.index', [
            'reports' => Report::where('report_type', 'violation')->latest()->paginate(15),
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
