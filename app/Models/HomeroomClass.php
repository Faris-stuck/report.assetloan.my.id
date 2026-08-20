<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeroomClass extends Model
{
    protected $fillable = ['homeroom_user_id', 'class_id', 'academic_year'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_user_id');
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}
