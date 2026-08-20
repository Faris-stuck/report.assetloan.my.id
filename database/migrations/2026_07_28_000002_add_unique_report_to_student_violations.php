<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_violations', function (Blueprint $table): void {
            $table->unique('report_id', 'student_violations_report_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('student_violations', function (Blueprint $table): void {
            $table->dropUnique('student_violations_report_id_unique');
        });
    }
};
