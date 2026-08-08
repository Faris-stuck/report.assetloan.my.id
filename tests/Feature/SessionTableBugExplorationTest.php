<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionTableBugExplorationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create test users without full seeding to avoid timeout
    }

    /**
     * Bug Condition: Session table missing causes login to fail
     * 
     * Validates: Requirements 1.1, 1.2
     * 
     * This test explores the bug condition where the sessions table
     * is not available in the database, causing login operations to fail.
     * The test is expected to FAIL on unfixed code with:
     * "Base table or view not found: 1146 Table 'laporin.sessions' doesn't exist"
     * 
     * @test
     */
    public function test_sessions_table_exists_and_is_accessible(): void
    {
        // Bug Condition: Verify sessions table exists
        // Expected to FAIL on unfixed code if sessions table missing
        $this->assertTrue(
            Schema::hasTable('sessions'),
            'Sessions table must exist in database. Error: Table laporin.sessions does not exist'
        );

        // Verify sessions table has all required columns
        $this->assertTrue(Schema::hasColumns('sessions', [
            'id',
            'user_id',
            'ip_address',
            'user_agent',
            'payload',
            'last_activity'
        ]), 'Sessions table must have all required columns');
    }

    /**
     * Bug Condition: Login fails when trying to create session
     * 
     * Validates: Requirements 1.1, 1.2
     * 
     * This test attempts to login with valid credentials and expects
     * the session to be created successfully. The test is expected to
     * FAIL on unfixed code if the sessions table doesn't exist.
     * 
     * @test
     */
    public function test_login_creates_session_successfully(): void
    {
        // Create test user directly without seeding
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'siswa',
            'is_active' => true
        ]);

        // Bug Condition: Attempt to login with valid credentials
        // Expected to FAIL on unfixed code: "Table 'laporin.sessions' doesn't exist"
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        // Verify user is authenticated after login
        $response->assertRedirect();
        $this->assertAuthenticated();

        // Verify session data was created in sessions table
        $this->assertDatabaseHas('sessions', [
            'user_id' => $user->id
        ]);

        // Verify session contains valid user_agent and ip_address
        $session = \DB::table('sessions')
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($session, 'Session record should exist after login');
        $this->assertNotEmpty($session->user_agent, 'Session user_agent should be populated');
        $this->assertNotEmpty($session->ip_address, 'Session ip_address should be populated');
    }

    /**
     * Bug Condition: Session data retrievable on subsequent requests
     * 
     * Validates: Requirements 1.2
     * 
     * This test verifies that after login, the session can be retrieved
     * on subsequent requests without errors. Expected to FAIL on unfixed
     * code if sessions table missing or not accessible.
     * 
     * @test
     */
    public function test_session_retrievable_on_subsequent_requests(): void
    {
        // Create and login user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'siswa',
            'is_active' => true
        ]);

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $this->assertAuthenticated();

        // Bug Condition: Access protected route to verify session retrieval
        // Expected to FAIL on unfixed code if session cannot be retrieved
        $response = $this->get('/dashboard');

        // Should be able to access protected route with valid session
        $response->assertStatus(200);

        // Verify still authenticated after accessing subsequent route
        $this->assertAuthenticated();
    }

    /**
     * Bug Condition: Session response time <50ms
     * 
     * Validates: Requirements 1.2
     * 
     * This test measures the time it takes to retrieve session data
     * and expects it to be fast (<50ms). On unfixed code, this will
     * either FAIL (if sessions table missing) or show slow performance.
     * 
     * @test
     */
    public function test_session_access_response_time_under_50ms(): void
    {
        // Create and login user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'siswa',
            'is_active' => true
        ]);

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $this->assertAuthenticated();

        // Bug Condition: Measure session access time
        // Expected to FAIL on unfixed code if sessions table missing
        // Or show slow performance if database not optimized
        $start = microtime(true);
        $response = $this->get('/dashboard');
        $elapsed_ms = (microtime(true) - $start) * 1000;

        // Session retrieval should be fast
        // Typical database session access: <50ms
        // Note: On unfixed code, may be slow (150-600ms) due to no cache layer
        $this->assertLessThan(50, $elapsed_ms, 
            "Session access should be under 50ms, but took {$elapsed_ms}ms. " .
            "Bug Condition: Session table missing or no cache layer causes slow access"
        );

        $response->assertStatus(200);
    }

    /**
     * Bug Condition: Session logout destroys session properly
     * 
     * Validates: Requirements 1.2
     * 
     * This test verifies that session is properly destroyed when user logs out.
     * Expected behavior: session record deleted from sessions table.
     * 
     * @test
     */
    public function test_session_destroyed_on_logout(): void
    {
        // Create and login user
        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'siswa',
            'is_active' => true
        ]);

        $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        $this->assertAuthenticated();

        // Verify session exists
        $this->assertDatabaseHas('sessions', [
            'user_id' => $user->id
        ]);

        // Bug Condition: Logout should destroy session
        $response = $this->post('/logout');

        // Should be logged out
        $this->assertGuest();

        // Session should be destroyed/removed
        $this->assertDatabaseMissing('sessions', [
            'user_id' => $user->id
        ]);
    }
}
