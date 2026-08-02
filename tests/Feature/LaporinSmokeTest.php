<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class LaporinSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_report_page_can_be_rendered(): void
    {
        $this->get('/')->assertOk()->assertSee('LAPORIN');
    }

    public function test_tracking_page_can_be_rendered(): void
    {
        $this->get('/lacak')->assertOk()->assertSee('Lacak Laporan');
    }

    public function test_superadmin_can_login_with_email_and_open_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'superadmin',
            'is_active' => true,
            'email' => 'admin@example.test',
            'password' => bcrypt('password'),
        ]);

        Session::start();

        $this->post('/login', [
            '_token' => csrf_token(),
            'login' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk()->assertSee('Dashboard');
    }
}
