<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DamageCategory extends Model
{
    protected $fillable = ['category_name', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
