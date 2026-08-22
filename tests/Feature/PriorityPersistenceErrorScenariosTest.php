<?php

namespace Tests\Feature;

use App\Models\DamageDetail;
use App\Models\Report;
use App\Models\SchoolClass;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Test: Priority Persistence in Error Scenarios (Task 1.5)
 * 
 * Tests that priority persists correctly in error scenarios and that the 
 * NULL initialization doesn't cause regressions.
 * 
 * **Validates: Requirements 2.2, 2.3**
 */
class PriorityPersistenceErrorScenariosTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test 1: Create damage report with complete valid data
     * Verify damage_detail.priority = NULL (not from urgency)
     * Verify reports.urgency = 'darurat' (from step 3)
     */
    public function test_create_damage_report_with_complete_valid_data(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();

        $response = $this->withSession([
            'math_captcha_answer' => 8,
            'report_submit_token' => Str::uuid()->toString()
        ])->post(route('public.report.store'), [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Reporter Complete',
            'reporter_phone' => '+62812345678901',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Complete Valid Damage Report',
            'incident_date' => now()->toDateString(),
            'description' => 'A complete and valid damage report.',
            'urgency' => 'darurat',
            'item_name' => 'Laptop Kerusakan',
            'damage_condition' => 'Kerusakan total pada layar.',
            'consent' => '1',
            'captcha' => '8',
        ]);

        $response->assertRedirect();
        $report = Report::where('title', 'Complete Valid Damage Report')->firstOrFail();
        $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

        $this->assertNull($damageDetail->priority);
        $this->assertSame('darurat', $report->urgency);
        $this->assertNotEquals($damageDetail->priority, $report->urgency);
    }

    /**
     * Test 2: Database transaction rollback on error
     * Attempt to create damage report with invalid foreign key
     */
    public function test_database_transaction_rollback_on_error(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();

        $this->withSession([
            'math_captcha_answer' => 8,
            'report_submit_token' => Str::uuid()->toString()
        ])->post(route('public.report.store'), [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Rollback',
            'reporter_phone' => '+62812345678902',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Should Be Rolled Back',
            // FK tidak valid: kelas terkait yang tidak ada. Divalidasi lewat aturan
            // exists, jadi tidak ada satu pun baris yang boleh tertulis.
            'related_class_id' => 999999,
            'incident_date' => now()->toDateString(),
            'description' => 'This report should rollback.',
            'urgency' => 'tinggi',
            'item_name' => 'Test Item',
            'damage_condition' => 'Test damage.',
            'consent' => '1',
            'captcha' => '8',
        ]);

        $this->assertDatabaseMissing('reports', ['title' => 'Should Be Rolled Back']);
        $this->assertDatabaseMissing('damage_details', ['item_name' => 'Test Item']);
    }

    /**
     * Test 3: Priority update via direct model update persists
     */
    public function test_priority_update_persists(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();

        $response = $this->withSession([
            'math_captcha_answer' => 8,
            'report_submit_token' => Str::uuid()->toString()
        ])->post(route('public.report.store'), [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Sarpras Update',
            'reporter_phone' => '+62812345678903',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Report for Sarpras Update',
            'incident_date' => now()->toDateString(),
            'description' => 'This report will be updated by Sarpras.',
            'urgency' => 'sedang',
            'item_name' => 'Computer',
            'damage_condition' => 'Screen malfunction.',
            'consent' => '1',
            'captcha' => '8',
        ]);

        $response->assertRedirect();
        $report = Report::where('title', 'Report for Sarpras Update')->firstOrFail();
        $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

        $this->assertNull($damageDetail->priority);
        $this->assertSame('sedang', $report->urgency);

        $damageDetail->update(['priority' => 'tinggi']);
        $updatedDetail = DamageDetail::findOrFail($damageDetail->id);
        $updatedReport = Report::findOrFail($report->id);

        $this->assertSame('tinggi', $updatedDetail->priority);
        $this->assertSame('sedang', $updatedReport->urgency);
        $this->assertNotEquals($updatedDetail->priority, $updatedReport->urgency);
    }

    /**
     * Test 4: Form submission still works
     */
    public function test_regression_form_submission_still_works(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();

        $response = $this->withSession([
            'math_captcha_answer' => 8,
            'report_submit_token' => Str::uuid()->toString()
        ])->post(route('public.report.store'), [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Form Regression',
            'reporter_phone' => '+62812345678904',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Form Regression Test',
            'incident_date' => now()->toDateString(),
            'description' => 'Testing form regression.',
            'urgency' => 'darurat',
            'item_name' => 'Projector',
            'damage_condition' => 'Not working.',
            'consent' => '1',
            'captcha' => '8',
        ]);

        $response->assertRedirect();
        $report = Report::where('title', 'Form Regression Test')->firstOrFail();
        $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

        $this->assertSame('menunggu_verifikasi', $report->status);
        $this->assertNull($damageDetail->priority);
        $this->assertDatabaseHas('report_status_histories', [
            'report_id' => $report->id,
            'new_status' => 'menunggu_verifikasi',
        ]);
    }

    /**
     * Test 5: Multiple urgency values still work
     */
    public function test_regression_multiple_urgency_values_still_work(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();
        $urgencyValues = ['rendah', 'sedang', 'tinggi', 'darurat'];

        foreach ($urgencyValues as $urgency) {
            $response = $this->withSession([
                'math_captcha_answer' => 8,
                'report_submit_token' => Str::uuid()->toString()
            ])->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => "Test {$urgency}",
                'reporter_phone' => '+62812345678' . random_int(900, 999),
                'reporter_class_id' => $class->id,
                'report_type' => 'damage',
                'title' => "Damage Report {$urgency}",
                'incident_date' => now()->toDateString(),
                'description' => "Test report with urgency {$urgency}.",
                'urgency' => $urgency,
                'item_name' => "Item {$urgency}",
                'damage_condition' => 'Kerusakan.',
                'consent' => '1',
                'captcha' => '8',
            ]);

            $response->assertRedirect();
            $report = Report::where('title', "Damage Report {$urgency}")->firstOrFail();
            $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

            $this->assertSame($urgency, $report->urgency);
            $this->assertNull($damageDetail->priority);
        }
    }

    /**
     * Test 6: Violation reports (no priority field) still work
     */
    public function test_regression_violation_reports_still_work(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();

        $response = $this->withSession([
            'math_captcha_answer' => 8,
            'report_submit_token' => Str::uuid()->toString()
        ])->post(route('public.report.store'), [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Violation Reporter',
            'reporter_phone' => '+62812345678905',
            'reporter_class_id' => $class->id,
            'related_class_id' => $class->id,
            'report_type' => 'violation',
            'title' => 'Violation Report Test',
            'incident_date' => now()->toDateString(),
            'description' => 'Test violation report.',
            'urgency' => 'tinggi',
            'reporter_position' => 'korban',
            'bullying_type' => 'verbal',
            'victim_name' => 'Victim Test',
            'victim_class_id' => $class->id,
            'alleged_actor_name' => 'Actor Test',
            'alleged_actor_class_id' => $class->id,
            'consent' => '1',
            'captcha' => '8',
        ]);

        $response->assertRedirect();
        $report = Report::where('title', 'Violation Report Test')->firstOrFail();

        $this->assertSame('violation', $report->report_type);
        $this->assertSame('kesiswaan', $report->assigned_to_role);
        $this->assertNotNull($report->bullyingDetail);
        $this->assertNull($report->damageDetail);
    }

    /**
     * Test 7: Priority NULL persists across multiple queries
     */
    public function test_priority_null_persists_across_multiple_queries(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();

        $response = $this->withSession([
            'math_captcha_answer' => 8,
            'report_submit_token' => Str::uuid()->toString()
        ])->post(route('public.report.store'), [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Persistence',
            'reporter_phone' => '+62812345678906',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Persistence Test Report',
            'incident_date' => now()->toDateString(),
            'description' => 'Testing persistence.',
            'urgency' => 'tinggi',
            'item_name' => 'Test Item',
            'damage_condition' => 'Test condition.',
            'consent' => '1',
            'captcha' => '8',
        ]);

        $response->assertRedirect();
        $report = Report::where('title', 'Persistence Test Report')->firstOrFail();

        $this->assertNull($report->damageDetail->priority);
        $this->assertNull(DamageDetail::where('report_id', $report->id)->firstOrFail()->priority);
        $this->assertNull(DamageDetail::findOrFail($report->damageDetail->id)->priority);
        $this->assertNull($report->fresh()->damageDetail->priority);
    }

    /**
     * Test 8: Updating urgency doesn't affect priority
     */
    public function test_updating_urgency_does_not_affect_priority(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();

        $response = $this->withSession([
            'math_captcha_answer' => 8,
            'report_submit_token' => Str::uuid()->toString()
        ])->post(route('public.report.store'), [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Urgency Update',
            'reporter_phone' => '+62812345678907',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Urgency Update Test',
            'incident_date' => now()->toDateString(),
            'description' => 'Testing urgency update.',
            'urgency' => 'sedang',
            'item_name' => 'Item Update Test',
            'damage_condition' => 'Initial condition.',
            'consent' => '1',
            'captcha' => '8',
        ]);

        $response->assertRedirect();
        $report = Report::where('title', 'Urgency Update Test')->firstOrFail();
        $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

        $this->assertNull($damageDetail->priority);
        $this->assertSame('sedang', $report->urgency);

        $report->update(['urgency' => 'darurat']);
        $updatedReport = Report::findOrFail($report->id);
        $updatedDamageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

        $this->assertSame('darurat', $updatedReport->urgency);
        $this->assertNull($updatedDamageDetail->priority);
    }

    /**
     * Test 9: Bulk damage report creation maintains priority NULL
     */
    public function test_bulk_damage_report_creation_maintains_priority_null(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();

        for ($i = 1; $i <= 5; $i++) {
            $this->withSession([
                'math_captcha_answer' => 8,
                'report_submit_token' => Str::uuid()->toString()
            ])->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => "Test Bulk {$i}",
                'reporter_phone' => '+62812345678' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'reporter_class_id' => $class->id,
                'report_type' => 'damage',
                'title' => "Bulk Report {$i}",
                'incident_date' => now()->toDateString(),
                'description' => "Bulk test {$i}.",
                'urgency' => ['rendah', 'sedang', 'tinggi', 'darurat', 'sedang'][$i - 1],
                'item_name' => "Item {$i}",
                'damage_condition' => "Condition {$i}.",
                'consent' => '1',
                'captcha' => '8',
            ]);
        }

        DamageDetail::all()->each(fn($detail) => $this->assertNull($detail->priority));
    }

    /**
     * Test 10: Priority NULL allows independent admin updates
     */
    public function test_priority_null_allows_independent_admin_updates(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();

        $response = $this->withSession([
            'math_captcha_answer' => 8,
            'report_submit_token' => Str::uuid()->toString()
        ])->post(route('public.report.store'), [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Admin Update',
            'reporter_phone' => '+62812345678908',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Admin Update Test',
            'incident_date' => now()->toDateString(),
            'description' => 'Testing admin priority update.',
            'urgency' => 'sedang',
            'item_name' => 'Admin Test Item',
            'damage_condition' => 'Admin test condition.',
            'consent' => '1',
            'captcha' => '8',
        ]);

        $response->assertRedirect();
        $report = Report::where('title', 'Admin Update Test')->firstOrFail();
        $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

        $this->assertNull($damageDetail->priority);
        $damageDetail->update(['priority' => 'tinggi']);

        $updated = DamageDetail::findOrFail($damageDetail->id);
        $this->assertSame('tinggi', $updated->priority);
        $this->assertSame('sedang', Report::findOrFail($report->id)->urgency);
    }

    /**
     * Test 11: Database migration supports nullable priority
     */
    public function test_database_migration_supports_nullable_priority(): void
    {
        $class = SchoolClass::firstOrFail();

        $report = Report::create([
            'report_number' => 'LAP-TEST-' . uniqid(),
            'public_token' => Str::uuid()->toString(),
            'access_code_hash' => bcrypt('123456'),
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Migration',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Migration Test',
            'incident_date' => now()->toDateString(),
            'description' => 'Testing nullable priority.',
            'urgency' => 'tinggi',
            'status' => 'menunggu_verifikasi',
            'assigned_to_role' => 'sarpras',
            'consent_accepted_at' => now(),
        ]);

        $damageDetail = DamageDetail::create([
            'report_id' => $report->id,
            'item_name' => 'Test Item',
            'item_category' => 'electronic',
            'damage_condition' => 'Test condition',
            'suspected_cause' => 'Test cause',
            'priority' => null,
        ]);

        $this->assertNull(DamageDetail::findOrFail($damageDetail->id)->priority);
        $damageDetail->update(['priority' => 'tinggi']);
        $this->assertSame('tinggi', $damageDetail->fresh()->priority);
        $damageDetail->update(['priority' => null]);
        $this->assertNull($damageDetail->fresh()->priority);
    }

    /**
     * Test 12: Concurrent priority updates preserve urgency independence
     */
    public function test_concurrent_priority_updates_preserve_urgency(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();

        $response = $this->withSession([
            'math_captcha_answer' => 8,
            'report_submit_token' => Str::uuid()->toString()
        ])->post(route('public.report.store'), [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Concurrent',
            'reporter_phone' => '+62812345678909',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Concurrent Update Test',
            'incident_date' => now()->toDateString(),
            'description' => 'Testing concurrent updates.',
            'urgency' => 'darurat',
            'item_name' => 'Concurrent Item',
            'damage_condition' => 'Concurrent condition.',
            'consent' => '1',
            'captcha' => '8',
        ]);

        $response->assertRedirect();
        $report = Report::where('title', 'Concurrent Update Test')->firstOrFail();
        $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

        foreach (['rendah', 'sedang', 'tinggi'] as $priority) {
            $damageDetail->update(['priority' => $priority]);
            $this->assertSame($priority, $damageDetail->fresh()->priority);
            $this->assertSame('darurat', $report->fresh()->urgency);
        }
    }

    /**
     * Test 13: Priority NULL for various reporter types
     */
    public function test_priority_null_for_various_reporter_types(): void
    {
        Storage::fake('private');
        $class = SchoolClass::firstOrFail();
        $staffUnit = \App\Models\StaffUnit::firstOrFail();
        $subject = \App\Models\Subject::where('is_active', true)->firstOrFail();

        $reporterConfigs = [
            ['type' => 'siswa', 'name' => 'Siswa', 'phone' => '+62812345678910', 'class_id' => $class->id],
            ['type' => 'guru', 'name' => 'Guru', 'phone' => '+62812345678911', 'subject_id' => $subject->id],
            ['type' => 'staff', 'name' => 'Staff', 'phone' => '+62812345678912', 'staff_unit_id' => $staffUnit->id],
        ];

        foreach ($reporterConfigs as $config) {
            $postData = [
                'reporter_type' => $config['type'],
                'reporter_name' => $config['name'],
                'reporter_phone' => $config['phone'],
                'report_type' => 'damage',
                'title' => "Damage from {$config['type']}",
                'incident_date' => now()->toDateString(),
                'description' => "Report from {$config['type']}.",
                'urgency' => 'tinggi',
                'item_name' => "Item from {$config['type']}",
                'damage_condition' => 'Test.',
                'consent' => '1',
                'captcha' => '8',
            ];

            if ($config['type'] === 'siswa') {
                $postData['reporter_class_id'] = $config['class_id'];
            } elseif ($config['type'] === 'guru') {
                $postData['reporter_subject_id'] = $config['subject_id'];
            } elseif ($config['type'] === 'staff') {
                $postData['reporter_staff_unit_id'] = $config['staff_unit_id'];
            }

            $response = $this->withSession([
                'math_captcha_answer' => 8,
                'report_submit_token' => Str::uuid()->toString()
            ])->post(route('public.report.store'), $postData);

            $response->assertRedirect();
            $report = Report::where('title', "Damage from {$config['type']}")->firstOrFail();
            $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();
            $this->assertNull($damageDetail->priority);
        }
    }
}
