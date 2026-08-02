<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentViolation extends Model
{
    protected $fillable = ['student_id', 'report_id', 'violation_type_id', 'point_reduced', 'note', 'processed_by_user_id'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function violationType(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }
}
