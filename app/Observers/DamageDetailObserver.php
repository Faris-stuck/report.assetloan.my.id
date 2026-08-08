<?php

namespace App\Observers;

use App\Models\DamageDetail;
use Illuminate\Support\Facades\Cache;

class DamageDetailObserver
{
    /**
     * Handle the DamageDetail "created" event.
     */
    public function created(DamageDetail $damageDetail): void
    {
        Cache::tags('damagedetails', 'reports', 'damage_categories')->flush();
    }

    /**
     * Handle the DamageDetail "updated" event.
     */
    public function updated(DamageDetail $damageDetail): void
    {
        Cache::tags('damagedetails', 'reports', 'damage_categories')->flush();
    }

    /**
     * Handle the DamageDetail "deleted" event.
     */
    public function deleted(DamageDetail $damageDetail): void
    {
        Cache::tags('damagedetails', 'reports', 'damage_categories')->flush();
    }

    /**
     * Handle the DamageDetail "forceDeleted" event.
     */
    public function forceDeleted(DamageDetail $damageDetail): void
    {
        Cache::tags('damagedetails', 'reports', 'damage_categories')->flush();
    }
}
