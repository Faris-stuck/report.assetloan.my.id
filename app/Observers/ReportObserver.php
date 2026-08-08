<?php

namespace App\Observers;

use App\Models\Report;
use Illuminate\Support\Facades\Cache;

class ReportObserver
{
    /**
     * Handle the Report "created" event.
     */
    public function created(Report $report): void
    {
        Cache::tags('reports', 'locations')->flush();
    }

    /**
     * Handle the Report "updated" event.
     */
    public function updated(Report $report): void
    {
        Cache::tags('reports', 'locations')->flush();
    }

    /**
     * Handle the Report "deleted" event.
     */
    public function deleted(Report $report): void
    {
        Cache::tags('reports', 'locations')->flush();
    }

    /**
     * Handle the Report "forceDeleted" event.
     */
    public function forceDeleted(Report $report): void
    {
        Cache::tags('reports', 'locations')->flush();
    }
}
