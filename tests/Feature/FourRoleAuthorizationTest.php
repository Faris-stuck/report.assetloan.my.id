<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FourRoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_four_internal_roles_are_supported(): void
    {
        $this->assertSame(
            ['superadmin', 'kesiswaan', 'sarpras', 'wali_kelas'],
            User::ROLES
        );

        $this->seed();

        $this->assertEqualsCanonicalizing(
            User::ROLES,
            User::query()->where('is_active', true)->distinct()->pluck('role')->all()
        );
        $this->assertDatabaseMissing('users', ['role' => 'guru', 'is_active' => true]);
        $this->assertDatabaseMissing('users', ['role' => 'siswa', 'is_active' => true]);
        $this->assertFalse(Route::has('siswa.point.pdf'));
    }

    public function test_navbar_only_shows_menus_relevant_to_each_role(): void
    {
        $this->seed();

        $kesiswaanNav = $this->navbarFor(User::where('role', 'kesiswaan')->firstOrFail());
        $this->assertStringContainsString('Dashboard', $kesiswaanNav);
        $this->assertStringContainsString('Kesiswaan', $kesiswaanNav);
        $this->assertStringNotContainsString('Buat Laporan', $kesiswaanNav);
        $this->assertStringNotContainsString(route('sarpras.index'), $kesiswaanNav);
        $this->assertStringNotContainsString('>Admin<', $kesiswaanNav);

        $sarprasNav = $this->navbarFor(User::where('role', 'sarpras')->firstOrFail());
        $this->assertStringContainsString('Dashboard', $sarprasNav);
        $this->assertStringContainsString('Sarpras', $sarprasNav);
        $this->assertStringNotContainsString('Buat Laporan', $sarprasNav);
        $this->assertStringNotContainsString(route('kesiswaan.index'), $sarprasNav);
        $this->assertStringNotContainsString('>Admin<', $sarprasNav);

        $waliNav = $this->navbarFor(User::where('role', 'wali_kelas')->firstOrFail());
        $this->assertStringContainsString('Dashboard', $waliNav);
        $this->assertStringNotContainsString('Buat Laporan', $waliNav);
        $this->assertStringNotContainsString(route('kesiswaan.index'), $waliNav);
        $this->assertStringNotContainsString(route('sarpras.index'), $waliNav);
        $this->assertStringNotContainsString('>Admin<', $waliNav);

        $adminNav = $this->navbarFor(User::where('role', 'superadmin')->firstOrFail());
        $this->assertStringContainsString('Dashboard', $adminNav);
        $this->assertStringContainsString(route('kesiswaan.index'), $adminNav);
        $this->assertStringContainsString(route('sarpras.index'), $adminNav);
        $this->assertStringContainsString('>Admin<', $adminNav);
        $this->assertStringNotContainsString('Buat Laporan', $adminNav);
    }

    public function test_admin_cannot_create_or_reactivate_legacy_roles(): void
    {
        $this->seed();
        $admin = User::where('role', 'superadmin')->firstOrFail();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Guru Tidak Valid',
            'email' => 'guru.invalid@example.test',
            'password' => 'password123',
            'role' => 'guru',
            'is_active' => '1',
        ])->assertSessionHasErrors(['role']);

        $legacy = User::forceCreate([
            'name' => 'Guru Legacy Terkunci',
            'email' => 'guru.locked@example.test',
            'password' => Hash::make('password123'),
            'role' => 'guru',
            'legacy_is_active' => true,
            'role_deactivated_at' => now(),
            'is_active' => false,
        ]);

        $this->actingAs($admin)->put(route('admin.users.update', $legacy), [
            'name' => $legacy->name,
            'email' => $legacy->email,
            'role' => 'wali_kelas',
            'is_active' => '1',
        ])->assertSessionHasErrors(['role']);

        $this->assertDatabaseHas('users', [
            'id' => $legacy->id,
            'role' => 'guru',
            'is_active' => false,
        ]);
    }

    public function test_inactive_accounts_are_logged_out_and_never_receive_superadmin_override(): void
    {
        $inactiveAdmin = User::forceCreate([
            'name' => 'SuperAdmin Nonaktif',
            'email' => 'inactive-admin@example.test',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'is_active' => false,
        ]);

        $this->actingAs($inactiveAdmin)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['auth']);
        $this->assertGuest();

        $this->assertFalse(
            Gate::forUser($inactiveAdmin)->allows('viewAny', User::class)
        );
    }

    public function test_legacy_guru_and_siswa_accounts_are_archived_without_being_deleted(): void
    {
        $migrationPath = database_path('migrations/2026_07_30_090000_reduce_users_to_four_roles.php');
        $this->assertFileExists($migrationPath);

        $migration = require $migrationPath;
        $migration->down();

        $guru = User::forceCreate([
            'name' => 'Guru Legacy',
            'email' => 'guru.legacy@example.test',
            'password' => Hash::make('password123'),
            'role' => 'guru',
            'is_active' => true,
        ]);
        $siswa = User::forceCreate([
            'name' => 'Siswa Legacy',
            'email' => 'siswa.legacy@example.test',
            'password' => Hash::make('password123'),
            'role' => 'siswa',
            'is_active' => true,
        ]);

        foreach ([$guru, $siswa] as $user) {
            DB::table('sessions')->insert([
                'id' => 'legacy-session-'.$user->id,
                'user_id' => $user->id,
                'payload' => 'legacy',
                'last_activity' => now()->timestamp,
            ]);
            DB::table('password_reset_tokens')->insert([
                'email' => $user->email,
                'token' => 'legacy-reset-token-'.$user->id,
                'created_at' => now(),
            ]);
        }

        $migration->up();

        foreach ([$guru->id => 'guru', $siswa->id => 'siswa'] as $id => $legacyRole) {
            $this->assertDatabaseHas('users', [
                'id' => $id,
                'role' => $legacyRole,
                'legacy_is_active' => true,
                'is_active' => false,
            ]);
            $this->assertNotNull(DB::table('users')->where('id', $id)->value('role_deactivated_at'));
            $this->assertDatabaseMissing('sessions', ['user_id' => $id]);
            $this->assertDatabaseMissing('password_reset_tokens', [
                'email' => User::withTrashed()->findOrFail($id)->email,
            ]);
        }

        $this->assertFalse(Schema::hasColumn('users', 'legacy_role'));

        $migration->down();
        foreach ([$guru->id => 'guru', $siswa->id => 'siswa'] as $id => $legacyRole) {
            $this->assertDatabaseHas('users', [
                'id' => $id,
                'role' => $legacyRole,
                'is_active' => true,
            ]);
        }
    }

    private function navbarFor(User $user): string
    {
        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $start = strpos($html, '<nav');
        $end = strpos($html, '</nav>');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start + strlen('</nav>'));
    }
}
