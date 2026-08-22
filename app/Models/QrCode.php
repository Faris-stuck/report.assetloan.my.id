<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCode extends Model
{
    protected $fillable = ['qr_identifier', 'qr_name', 'qr_type', 'class_id', 'target_url', 'is_active', 'scan_count', 'created_by'];

    protected $casts = ['is_active' => 'boolean'];

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
