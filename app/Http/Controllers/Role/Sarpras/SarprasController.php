<?php

namespace App\Http\Controllers\Role\Sarpras;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Role\Sarpras\SarprasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SarprasController extends Controller
{
    public function __construct(private SarprasService $service)
    {
    }

    public function index(): View
    {
        return $this->service->index();
    }

    public function process(Request $request, Report $report): RedirectResponse
    {
        return $this->service->process($request, $report);
    }

    public function reject(Request $request, Report $report): RedirectResponse
    {
        return $this->service->reject($request, $report);
    }
}
