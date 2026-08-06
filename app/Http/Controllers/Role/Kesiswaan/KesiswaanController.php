<?php

namespace App\Http\Controllers\Role\Kesiswaan;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Services\Role\Kesiswaan\KesiswaanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KesiswaanController extends Controller
{
    public function __construct(private KesiswaanService $service)
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

    public function complete(Request $request, Report $report): RedirectResponse
    {
        return $this->service->complete($request, $report);
    }
}
