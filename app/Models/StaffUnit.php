<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffUnit extends Model
{
    protected $fillable = ['unit_name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
