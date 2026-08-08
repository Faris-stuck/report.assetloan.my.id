<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['report_number', 'public_token', 'access_code_hash', 'qr_code_id', 'reporter_type', 'reporter_name', 'reporter_class_id', 'reporter_absence_number', 'reporter_subject_id', 'reporter_staff_unit_id', 'reporter_phone', 'reporter_email', 'report_type', 'title', 'related_class_id', 'location_id', 'custom_location', 'incident_date', 'incident_time', 'description', 'urgency', 'status', 'consent_accepted_at', 'rejection_reason', 'assigned_to_user_id', 'assigned_to_role', 'verified_by', 'verified_at', 'submitted_ip_hash', 'submitted_user_agent', 'violation_type_id', 'damage_category_id'];

    protected $casts = [
        'incident_date' => 'date',
        'consent_accepted_at' => 'datetime',
        'verified_at' => 'datetime',
        'reporter_email' => 'encrypted',
        'reporter_phone' => 'encrypted',
        'submitted_ip_hash' => 'encrypted',
        'submitted_user_agent' => 'encrypted:without-prefix',
    ];

    public function qrCode(): BelongsTo
    {
        return $this->belongsTo(QrCode::class);
    }

    public function reporterClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'reporter_class_id');
    }

    public function relatedClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'related_class_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function violationType(): BelongsTo
    {
        return $this->belongsTo(ViolationType::class);
    }

    public function damageCategory(): BelongsTo
    {
        return $this->belongsTo(DamageCategory::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function bullyingDetail(): HasOne
    {
        return $this->hasOne(BullyingDetail::class);
    }

    public function damageDetail(): HasOne
    {
        return $this->hasOne(DamageDetail::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ReportAttachment::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ReportNote::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(ReportStatusHistory::class);
    }
}
