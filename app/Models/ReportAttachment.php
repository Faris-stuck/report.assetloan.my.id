<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportAttachment extends Model
{
    protected $fillable = ['report_id', 'uploaded_by_user_id', 'uploader_type', 'original_name', 'stored_name', 'file_path', 'mime_type', 'file_size', 'attachment_type'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
