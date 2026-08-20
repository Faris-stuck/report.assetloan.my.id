<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViolationType extends Model
{
    protected $fillable = ['violation_name', 'point_reduction', 'description', 'created_by', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
