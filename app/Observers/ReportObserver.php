<?php

namespace App\Observers;

use App\Helpers\CacheHelper;
use App\Models\Report;

class ReportObserver
{
    public function created(Report $report): void
    {
        $this->clearCache();
    }

    public function updated(Report $report): void
    {
        $this->clearCache();
    }

    public function deleted(Report $report): void
    {
        $this->clearCache();
    }

    public function restored(Report $report): void
    {
        $this->clearCache();
    }

    public function forceDeleted(Report $report): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        CacheHelper::invalidateTags([
            'report',
            'location',
        ]);

        CacheHelper::invalidate('laporin:report:*');
    }
}