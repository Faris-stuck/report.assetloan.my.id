<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'legacy_is_active')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('legacy_is_active')->nullable()->after('role');
            });
        }

        if (! Schema::hasColumn('users', 'role_deactivated_at')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->timestamp('role_deactivated_at')->nullable()->after('legacy_is_active');
            });
        }

        $legacyUsers = DB::table('users')
            ->whereIn('role', ['guru', 'siswa'])
            ->whereNull('role_deactivated_at')
            ->get(['id', 'email']);

        if ($legacyUsers->isEmpty()) {
            return;
        }

        $legacyUserIds = $legacyUsers->pluck('id');
        $legacyEmails = $legacyUsers->pluck('email');

        DB::transaction(function () use ($legacyUserIds, $legacyEmails): void {
            DB::table('users')
                ->whereIn('id', $legacyUserIds)
                ->update([
                    'legacy_is_active' => DB::raw('is_active'),
                    'is_active' => false,
                    'role_deactivated_at' => now(),
                    'remember_token' => null,
                    'updated_at' => now(),
                ]);

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->whereIn('user_id', $legacyUserIds)->delete();
            }

            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->whereIn('email', $legacyEmails)->delete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role_deactivated_at')) {
            DB::table('users')
                ->whereIn('role', ['guru', 'siswa'])
                ->whereNotNull('role_deactivated_at')
                ->update([
                    'is_active' => DB::raw('COALESCE(legacy_is_active, 0)'),
                    'remember_token' => null,
                    'updated_at' => now(),
                ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('users', 'role_deactivated_at')) {
                $columns[] = 'role_deactivated_at';
            }
            if (Schema::hasColumn('users', 'legacy_is_active')) {
                $columns[] = 'legacy_is_active';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
