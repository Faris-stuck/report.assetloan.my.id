<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table): void {
            $table->id();
            $table->string('class_name');
            $table->string('grade_level', 20);
            $table->string('major')->nullable();
            $table->string('academic_year', 20);
            $table->string('room_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_units', function (Blueprint $table): void {
            $table->id();
            $table->string('unit_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('nis')->unique();
            $table->string('name');
            $table->foreignId('class_id')->constrained('classes')->restrictOnDelete();
            $table->string('parent_phone', 30)->nullable();
            $table->integer('point')->default(100);
            $table->timestamps();
        });

        Schema::create('homeroom_classes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('homeroom_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('academic_year', 20);
            $table->timestamps();
            $table->unique(['homeroom_user_id', 'class_id', 'academic_year']);
        });

        Schema::create('teacher_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->cascadeOnDelete();
            $table->string('academic_year', 20);
            $table->timestamps();
        });

        Schema::create('violation_types', function (Blueprint $table): void {
            $table->id();
            $table->string('violation_name');
            $table->integer('point_reduction');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('damage_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('category_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('qr_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('qr_identifier')->unique();
            $table->string('qr_name');
            $table->enum('qr_type', ['general', 'class'])->default('general');
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->text('target_url');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('scan_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->string('report_number')->unique();
            $table->uuid('public_token')->unique();
            $table->string('access_code_hash');
            $table->foreignId('qr_code_id')->nullable()->constrained('qr_codes')->nullOnDelete();
            $table->enum('reporter_type', ['siswa', 'guru', 'staff']);
            $table->string('reporter_name');
            $table->foreignId('reporter_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->unsignedInteger('reporter_absence_number')->nullable();
            $table->foreignId('reporter_subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('reporter_staff_unit_id')->nullable()->constrained('staff_units')->nullOnDelete();
            $table->string('reporter_phone', 30)->nullable();
            $table->string('reporter_email')->nullable();
            $table->enum('report_type', ['violation', 'damage'])->index();
            $table->string('title');
            $table->foreignId('related_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->date('incident_date');
            $table->time('incident_time')->nullable();
            $table->text('description');
            $table->enum('urgency', ['rendah', 'sedang', 'tinggi', 'darurat'])->default('sedang')->index();
            $table->enum('status', ['menunggu_verifikasi', 'memerlukan_informasi', 'diverifikasi', 'ditolak', 'ditugaskan', 'sedang_ditangani', 'menunggu_konfirmasi', 'dibuka_kembali', 'selesai', 'diarsipkan'])->default('menunggu_verifikasi')->index();
            $table->timestamp('consent_accepted_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('assigned_to_role', ['kesiswaan', 'sarpras'])->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('submitted_ip_hash')->nullable();
            $table->text('submitted_user_agent')->nullable();
            $table->foreignId('violation_type_id')->nullable()->constrained('violation_types')->nullOnDelete();
            $table->foreignId('damage_category_id')->nullable()->constrained('damage_categories')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('bullying_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->unique()->constrained('reports')->cascadeOnDelete();
            $table->string('reporter_position')->nullable();
            $table->string('bullying_type')->nullable();
            $table->string('victim_name')->nullable();
            $table->foreignId('victim_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('alleged_actor_name')->nullable();
            $table->foreignId('alleged_actor_class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->string('witness_name')->nullable();
            $table->text('impact_description')->nullable();
            $table->timestamps();
        });

        Schema::create('damage_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->unique()->constrained('reports')->cascadeOnDelete();
            $table->string('item_name');
            $table->string('item_category')->nullable();
            $table->text('damage_condition');
            $table->text('suspected_cause')->nullable();
            $table->enum('priority', ['rendah', 'sedang', 'tinggi', 'darurat'])->default('sedang');
            $table->timestamp('scheduled_repair_at')->nullable();
            $table->timestamp('repaired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('student_violations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('violation_type_id')->constrained('violation_types')->restrictOnDelete();
            $table->integer('point_reduced');
            $table->text('note')->nullable();
            $table->foreignId('processed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('report_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('uploader_type', ['reporter', 'superadmin', 'kesiswaan', 'sarpras', 'wali_kelas', 'guru']);
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('attachment_type')->default('evidence');
            $table->timestamps();
        });

        Schema::create('report_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('author_type', ['reporter', 'superadmin', 'kesiswaan', 'sarpras', 'wali_kelas', 'guru']);
            $table->text('note');
            $table->enum('visibility', ['internal', 'reporter_visible'])->default('internal');
            $table->timestamps();
        });

        Schema::create('report_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type');
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->text('public_note')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type');
            $table->string('action');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address_hash')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'report_status_histories', 'report_notes', 'report_attachments', 'student_violations', 'damage_details', 'bullying_details', 'reports', 'qr_codes', 'damage_categories', 'violation_types', 'teacher_assignments', 'homeroom_classes', 'students', 'staff_units', 'subjects', 'classes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
