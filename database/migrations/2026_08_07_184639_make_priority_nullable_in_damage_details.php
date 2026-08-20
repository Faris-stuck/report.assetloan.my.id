<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('damage_details', function (Blueprint $table) {
            // Allow priority to be NULL on initial creation; Sarpras staff sets it independently
            $table->enum('priority', ['rendah', 'sedang', 'tinggi', 'darurat'])->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('damage_details', function (Blueprint $table) {
            // Revert to default 'sedang' with not nullable
            $table->enum('priority', ['rendah', 'sedang', 'tinggi', 'darurat'])->default('sedang')->change();
        });
    }
};
