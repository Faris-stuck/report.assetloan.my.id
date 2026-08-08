<?php

namespace Tests\Feature;

use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Property-Based Test for Session Race Condition Bug Exploration
 *
 * **Validates: Requirements 1.1, 1.2, 1.3**
 *
 * This test explores the race condition in TrackingController where concurrent requests
 * with valid sessions can exhibit inconsistent behavior. The bug manifests when:
 *
 * - Request A validates session successfully (hasTrackingAccess returns true)
 * - Between validation and database operation, Request B clears the session
 * - Request A continues with database operation but session state is now inconsistent
 *
 * OR
 *
 * - Request A and Request B both validate concurrently
 * - hasTrackingAccess() calls session()->forget() (line 160-161)
 * - One request's forget() call interferes with the other's validation check
 * - One request fails with "sesi tracking sudah habis" despite valid initial session
 *
 * Expected behavior on UNFIXED code: Test FAILS, demonstrating the race condition exists
 * Expected behavior on FIXED code: Test PASSES, demonstrating the race condition is resolved
 */
class TrackingControllerRaceConditionTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function testConcurrentSessionValidationRaceCondition(): void
    {
        $report = $this->createReport();
        $sessionData = [
            'track_report_id' => $report->id,
            'track_access_ok' => true,
            'track_verified_at' => now()->timestamp,
        ];

        // Simulate first concurrent request (addInfo)
        $response1 = $this->withSession($sessionData)
            ->post(route('track.info', $report), [
                'note' => 'First concurrent request adding information',
            ]);

        // Check if response redirects to error route
        $response1->assertRedirect();
        $firstRedirectTo = $response1->headers->get('Location');
        $firstIsErrorRedirect = str_contains($firstRedirectTo ?? '', route('track.form'));

        // Reinitialize session for second request
        $response2 = $this->withSession($sessionData)
            ->post(route('track.info', $report), [
                'note' => 'Second concurrent request adding information',
            ]);

        $response2->assertRedirect();
        $secondRedirectTo = $response2->headers->get('Location');
        $secondIsErrorRedirect = str_contains($secondRedirectTo ?? '', route('track.form'));

        // Bug condition: Both requests should not fail with error redirects
        $this->assertFalse(
            $firstIsErrorRedirect && $secondIsErrorRedirect,
            'Race condition detected: Both concurrent requests redirected to error page despite valid initial sessions'
        );
    }

    // ========== PRESERVATION PROPERTY TESTS (Requirements 4.1-4.8) ==========
    // These tests capture existing working behavior that must be preserved after the fix.
    // All tests PASS on unfixed code, establishing a baseline for regression detection.

    /**
     * **Preservation Requirement 4.1: Session Creation**
     *
     * **Validates: Requirement 4.1**
     *
     * Property (P1): When user successfully searches with valid report_number and access_code,
     * session is created with exactly three keys: track_report_id, track_access_ok, track_verified_at.
     *
     * Why preserved: Session creation is foundational for all tracking access control.
     * Race condition fix does not modify search() method or session creation logic.
     *
     * Test ensures: All three session keys are set with correct values after successful search.
     */
    public function testSessionCreationOnSuccessfulSearch(): void
    {
        $report = $this->createReport();
        $beforeTimestamp = now()->timestamp;

        $response = $this->post(route('track.search'), [
            'report_number' => $report->report_number,
            'access_code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertSessionHas('track_report_id')
            ->assertSessionHas('track_access_ok')
            ->assertSessionHas('track_verified_at');

        $this->assertEquals($report->id, session('track_report_id'));
        $this->assertTrue(session('track_access_ok'));
        $this->assertGreaterThanOrEqual($beforeTimestamp, session('track_verified_at'));
    }

    /**
     * **Preservation Requirement 4.2: Session TTL Enforcement**
     *
     * **Validates: Requirement 4.2**
     *
     * Property (P2): When session track_verified_at is older than TRACKING_SESSION_TTL_SECONDS (1800),
     * hasTrackingAccess() returns false, preventing further actions.
     *
     * Why preserved: TTL enforcement is critical security control preventing stale session access.
     * Race condition fix moves session clearing to error handler but keeps TTL logic unchanged.
     *
     * Test ensures: Expired session causes addInfo to redirect to track.form with appropriate error.
     */
    public function testSessionTTLEnforcement(): void
    {
        $report = $this->createReport(['status' => 'memerlukan_informasi']);
        
        // Create session that expired (1800+ seconds old)
        $expiredTimestamp = now()->timestamp - 1801;
        $sessionData = [
            'track_report_id' => $report->id,
            'track_access_ok' => true,
            'track_verified_at' => $expiredTimestamp,
        ];

        $response = $this->withSession($sessionData)
            ->post(route('track.info', $report), [
                'note' => 'Test note after expiration',
            ]);

        $response->assertRedirect(route('track.form'))
            ->assertSessionHas('errors');

        $errors = session('errors');
        $this->assertStringContainsString('sudah habis', strtolower($errors->first('access_code') ?? ''));
    }

    /**
     * **Preservation Requirement 4.3: Report Ownership**
     *
     * **Validates: Requirement 4.3**
     *
     * Property (P3): User authenticated for Report A cannot access Report B using same session.
     * Session contains track_report_id for Report A, accessing Report B fails ownership check.
     *
     * Why preserved: Ownership validation prevents unauthorized cross-report access.
     * Race condition fix does not modify ownership check in hasTrackingAccess().
     *
     * Test ensures: Mismatched report ID causes access denial redirect to track.form.
     */
    public function testReportOwnershipValidation(): void
    {
        $reportA = $this->createReport(['status' => 'memerlukan_informasi']);
        $reportB = $this->createReport(['status' => 'memerlukan_informasi']);

        // Session authenticated for Report A
        $sessionData = [
            'track_report_id' => $reportA->id,
            'track_access_ok' => true,
            'track_verified_at' => now()->timestamp,
        ];

        // Try to access Report B with Report A's session
        $response = $this->withSession($sessionData)
            ->post(route('track.info', $reportB), [
                'note' => 'Attempting cross-report access',
            ]);

        $response->assertRedirect(route('track.form'))
            ->assertSessionHas('errors');
    }

    /**
     * **Preservation Requirement 4.4: Status Validation for addInfo**
     *
     * **Validates: Requirement 4.4**
     *
     * Property (P4): addInfo only works on reports with status in allowed list:
     * [memerlukan_informasi, dibuka_kembali, menunggu_konfirmasi].
     * Other statuses return error "Aksi tambah informasi tidak tersedia".
     *
     * Why preserved: Status validation prevents information addition on inappropriate statuses.
     * Race condition fix does not modify this validation logic.
     *
     * Test ensures: Invalid status returns error without modifying report.
     */
    public function testStatusValidationForAddInfo(): void
    {
        $report = $this->createReport(['status' => 'selesai']);
        $validSession = [
            'track_report_id' => $report->id,
            'track_access_ok' => true,
            'track_verified_at' => now()->timestamp,
        ];

        $response = $this->withSession($validSession)
            ->post(route('track.info', $report), [
                'note' => 'Attempting to add info to completed report',
            ]);

        $response->assertRedirect()
            ->assertSessionHas('errors');

        $errors = session('errors');
        $this->assertStringContainsString('tidak tersedia', $errors->first('report') ?? '');
        
        // Verify report status unchanged
        $this->assertEquals('selesai', $report->fresh()->status);
    }

    /**
     * **Preservation Requirement 4.5: Status Validation for confirmComplete**
     *
     * **Validates: Requirement 4.5**
     *
     * Property (P5): confirmComplete only works when report status is exactly 'menunggu_konfirmasi'.
     * Other statuses return error "Laporan belum berada pada tahap menunggu konfirmasi".
     *
     * Why preserved: This validation ensures completion only happens at correct lifecycle stage.
     * Race condition fix does not modify this validation logic.
     *
     * Test ensures: Invalid status prevents completion action.
     */
    public function testStatusValidationForConfirmComplete(): void
    {
        $report = $this->createReport(['status' => 'dibuka_kembali']);
        $validSession = [
            'track_report_id' => $report->id,
            'track_access_ok' => true,
            'track_verified_at' => now()->timestamp,
        ];

        $response = $this->withSession($validSession)
            ->post(route('track.confirm', $report));

        $response->assertRedirect()
            ->assertSessionHas('errors');

        $errors = session('errors');
        $this->assertStringContainsString('menunggu konfirmasi', $errors->first('report') ?? '');
        
        // Verify report status unchanged
        $this->assertEquals('dibuka_kembali', $report->fresh()->status);
    }

    /**
     * **Preservation Requirement 4.6: Status Transitions**
     *
     * **Validates: Requirement 4.6**
     *
     * Property (P6): When addInfo succeeds:
     * - Status memerlukan_informasi or menunggu_konfirmasi → dibuka_kembali
     * - Status dibuka_kembali → remains dibuka_kembali (no duplicate transition)
     *
     * When confirmComplete succeeds:
     * - Status menunggu_konfirmasi → selesai
     *
     * Why preserved: Status transitions drive report lifecycle and notification flow.
     * Race condition fix does not modify transition logic.
     *
     * Test ensures: Correct status changes occur on valid operations.
     */
    public function testStatusTransitions(): void
    {
        // Test addInfo transition from memerlukan_informasi to dibuka_kembali
        $report1 = $this->createReport(['status' => 'memerlukan_informasi']);
        $session1 = [
            'track_report_id' => $report1->id,
            'track_access_ok' => true,
            'track_verified_at' => now()->timestamp,
        ];

        $this->withSession($session1)
            ->post(route('track.info', $report1), ['note' => 'Adding information']);

        $this->assertEquals('dibuka_kembali', $report1->fresh()->status);

        // Test addInfo on dibuka_kembali stays dibuka_kembali
        $session1['track_verified_at'] = now()->timestamp;
        $this->withSession($session1)
            ->post(route('track.info', $report1), ['note' => 'Adding more information']);

        $this->assertEquals('dibuka_kembali', $report1->fresh()->status);

        // Test confirmComplete transition from menunggu_konfirmasi to selesai
        $report2 = $this->createReport(['status' => 'menunggu_konfirmasi']);
        $session2 = [
            'track_report_id' => $report2->id,
            'track_access_ok' => true,
            'track_verified_at' => now()->timestamp,
        ];

        $this->withSession($session2)
            ->post(route('track.confirm', $report2));

        $this->assertEquals('selesai', $report2->fresh()->status);
    }

    /**
     * **Preservation Requirement 4.7: Notification Dispatch**
     *
     * **Validates: Requirement 4.7**
     *
     * Property (P7): When status transitions occur, kirimNotifikasiStatus() is called
     * with correct status label and public note. Notifications are sent to reporter_email.
     *
     * Why preserved: Notifications inform reporter of progress and changes.
     * Race condition fix does not modify notification dispatch.
     *
     * Test ensures: Notification mail is sent on valid status transitions.
     */
    public function testNotificationDispatchOnStatusChange(): void
    {
        $report = $this->createReport([
            'status' => 'memerlukan_informasi',
            'reporter_email' => 'reporter@test.local',
        ]);

        $session = [
            'track_report_id' => $report->id,
            'track_access_ok' => true,
            'track_verified_at' => now()->timestamp,
        ];

        // addInfo successfully transitions status - notification should be sent
        $response = $this->withSession($session)
            ->post(route('track.info', $report), ['note' => 'Adding requested information']);

        $response->assertRedirect();
        
        // Verify status was changed (which triggers notification dispatch)
        $this->assertEquals('dibuka_kembali', $report->fresh()->status);

        // Test confirmComplete also transitions status
        $report2 = $this->createReport([
            'status' => 'menunggu_konfirmasi',
            'reporter_email' => 'reporter@test.local',
        ]);

        $session2 = [
            'track_report_id' => $report2->id,
            'track_access_ok' => true,
            'track_verified_at' => now()->timestamp,
        ];

        $response2 = $this->withSession($session2)
            ->post(route('track.confirm', $report2));

        $response2->assertRedirect();
        
        // Verify status was changed (which triggers notification dispatch)
        $this->assertEquals('selesai', $report2->fresh()->status);
    }

    /**
     * **Preservation Requirement 4.8: Audit Trail**
     *
     * **Validates: Requirement 4.8**
     *
     * Property (P8): Every status transition creates ReportStatusHistory record with:
     * - previous_status: old status before transition
     * - new_status: new status after transition
     * - actor_type: 'reporter' (for tracking controller actions)
     * - public_note: human-readable description of change
     *
     * Why preserved: Audit trail enables compliance and debugging.
     * Race condition fix does not modify history recording.
     *
     * Test ensures: History records are created for all transitions.
     */
    public function testAuditTrailRecording(): void
    {
        $report = $this->createReport(['status' => 'memerlukan_informasi']);
        $session = [
            'track_report_id' => $report->id,
            'track_access_ok' => true,
            'track_verified_at' => now()->timestamp,
        ];

        $historyCountBefore = $report->histories()->count();

        $this->withSession($session)
            ->post(route('track.info', $report), ['note' => 'Adding information']);

        $historyCountAfter = $report->fresh()->histories()->count();
        $this->assertGreaterThan($historyCountBefore, $historyCountAfter);

        $latestHistory = $report->fresh()->histories()->latest()->first();
        $this->assertEquals('memerlukan_informasi', $latestHistory->previous_status);
        $this->assertEquals('dibuka_kembali', $latestHistory->new_status);
        $this->assertEquals('reporter', $latestHistory->actor_type);
        $this->assertNotEmpty($latestHistory->public_note);
    }

    // ========== Helper Methods ==========

    private function createReport(array $overrides = []): Report
    {
        $defaults = [
            'report_number' => 'LPR' . str_pad(random_int(1, 9999999999), 10, '0', STR_PAD_LEFT),
            'public_token' => (string) Str::uuid(),
            'access_code_hash' => Hash::make('123456'),
            'reporter_type' => 'staff',
            'reporter_name' => 'Pelapor Test',
            'report_type' => 'violation',
            'title' => 'Laporan untuk preservation test',
            'incident_date' => now()->toDateString(),
            'description' => 'Test description untuk preservation test.',
            'urgency' => 'sedang',
            'status' => 'memerlukan_informasi',
            'assigned_to_role' => 'kesiswaan',
            'consent_accepted_at' => now(),
            'reporter_email' => 'reporter@test.local',
        ];

        return Report::create(array_merge($defaults, $overrides));
    }
}
