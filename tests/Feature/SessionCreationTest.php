<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_created_on_successful_login(): void
    {
        $user = User::factory()->create([
            'email' => 'testuser@test.com',
            'password' => Hash::make('testpass123'),
            'is_active' => true,
            'role' => 'superadmin',
        ]);

        $this->get('/login')->assertOk();

        $response = $this->post('/login', [
            'login' => 'testuser@test.com',
            'password' => 'testpass123',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty(session()->getId());
    }

    public function test_session_has_correct_attributes(): void
    {
        $user = User::factory()->create([
            'email' => 'sessiontest@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'role' => 'kesiswaan',
        ]);

        $this->get('/login')->assertOk();

        $this->post('/login', [
            'login' => 'sessiontest@example.com',
            'password' => 'password123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotEmpty(session()->getId());
    }

    public function test_authenticated_user_can_access_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'dashtest@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'role' => 'superadmin',
        ]);

        $this->get('/login')->assertOk();

        $this->post('/login', [
            'login' => 'dashtest@test.com',
            'password' => 'password123',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);

        $this->get('/dashboard')
            ->assertOk();
    }
}
