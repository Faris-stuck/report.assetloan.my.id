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
        return view('sarpras.index', [
            'reports' => Report::where('report_type', 'damage')->with('damageDetail')->latest()->paginate(15),
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
