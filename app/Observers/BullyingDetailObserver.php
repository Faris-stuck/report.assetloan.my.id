<?php

namespace App\Observers;

use App\Models\BullyingDetail;
use Illuminate\Support\Facades\Cache;

class BullyingDetailObserver
{
    /**
     * Handle the BullyingDetail "created" event.
     */
    public function created(BullyingDetail $bullyingDetail): void
    {
        Cache::tags('bullyingdetails', 'reports', 'violation_types')->flush();
    }

    /**
     * Handle the BullyingDetail "updated" event.
     */
    public function updated(BullyingDetail $bullyingDetail): void
    {
        Cache::tags('bullyingdetails', 'reports', 'violation_types')->flush();
    }

    /**
     * Handle the BullyingDetail "deleted" event.
     */
    public function deleted(BullyingDetail $bullyingDetail): void
    {
        Cache::tags('bullyingdetails', 'reports', 'violation_types')->flush();
    }

    /**
     * Handle the BullyingDetail "forceDeleted" event.
     */
    public function forceDeleted(BullyingDetail $bullyingDetail): void
    {
        Cache::tags('bullyingdetails', 'reports', 'violation_types')->flush();
    }
}
