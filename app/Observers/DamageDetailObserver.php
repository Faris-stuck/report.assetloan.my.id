<?php

namespace App\Observers;

use App\Helpers\CacheHelper;
use App\Models\DamageDetail;

class DamageDetailObserver
{
    public function created(DamageDetail $damageDetail): void
    {
        $this->clearCache();
    }

    public function updated(DamageDetail $damageDetail): void
    {
        $this->clearCache();
    }

    public function deleted(DamageDetail $damageDetail): void
    {
        $this->clearCache();
    }

    public function restored(DamageDetail $damageDetail): void
    {
        $this->clearCache();
    }

    public function forceDeleted(DamageDetail $damageDetail): void
    {
        $this->clearCache();
    }

    private function clearCache(): void
    {
        CacheHelper::invalidateTags([
            'damagedetail',
            'report',
            'damagecategory',
        ]);

        CacheHelper::invalidate('laporin:damagedetail:*');
        CacheHelper::invalidate('laporin:report:*');
    }
}