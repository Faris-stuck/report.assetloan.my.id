<?php

namespace App\Observers;

use App\Helpers\CacheHelper;
use App\Models\BullyingDetail;

class BullyingDetailObserver
{
    public function created(BullyingDetail $bullyingDetail): void
    {
        $this->clearCache();
    }

    public function updated(BullyingDetail $bullyingDetail): void
    {
        $this->clearCache();
    }

    public function deleted(BullyingDetail $bullyingDetail): void
    {
        $this->clearCache();
    }

    public function restored(BullyingDetail $bullyingDetail): void
    {
        $this->clearCache();
    }

    public function forceDeleted(BullyingDetail $bullyingDetail): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        CacheHelper::invalidateTags([
            'bullyingdetail',
            'report',
            'violationtype',
        ]);

        CacheHelper::invalidate('laporin:bullyingdetail:*');
        CacheHelper::invalidate('laporin:report:*');
    }
}