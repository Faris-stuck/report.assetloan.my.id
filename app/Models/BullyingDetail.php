<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\SchoolClass;

class BullyingDetail extends Model
{
    protected $fillable = ['report_id', 'reporter_position', 'bullying_type', 'victim_name', 'victim_class_id', 'alleged_actor_name', 'alleged_actor_class_id', 'witness_name', 'impact_description'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function allegedActorClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'alleged_actor_class_id');
    }
}
