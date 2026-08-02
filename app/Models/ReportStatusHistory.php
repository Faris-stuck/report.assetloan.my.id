<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportStatusHistory extends Model
{
    protected $fillable = ['report_id', 'changed_by_user_id', 'actor_type', 'previous_status', 'new_status', 'public_note', 'internal_note'];
}
