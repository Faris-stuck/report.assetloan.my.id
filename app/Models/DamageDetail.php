<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageDetail extends Model
{
    protected $fillable = ['report_id', 'item_name', 'item_category', 'damage_condition', 'suspected_cause', 'priority', 'scheduled_repair_at', 'repaired_at'];

    protected $casts = ['scheduled_repair_at' => 'datetime', 'repaired_at' => 'datetime'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
