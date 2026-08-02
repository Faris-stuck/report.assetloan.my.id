<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'actor_type', 'action', 'model_type', 'model_id', 'old_values', 'new_values', 'ip_address_hash', 'user_agent'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];
}
