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
 * Property-Based Test: Bug Condition - Priority Data Persistence Defect
 * 
 * This test explores and documents the priority persistence bug.
 * **Expected behavior (after fix)**: damage_detail.priority should be NULL
 * **Actual behavior (unfixed code)**: damage_detail.priority equals the urgency value
 * 
 * This test MUST FAIL on unfixed code - this failure proves the bug exists.
 * **Validates: Requirements 1.2, 2.2**
 */
class PriorityPersistenceBugExplorationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Property 1: Bug Condition - Priority Data Persistence Defect
     * 
     * For any damage report creation where a public user selects urgency level in step 3 
     * and submits the form, the UNFIXED code creates damage_detail.priority from urgency 
     * instead of leaving it NULL (independent).
     * 
     * This test generates multiple urgency values and verifies each one produces the bug.
     */
    public function test_damage_report_priority_incorrectly_mirrors_urgency_on_creation(): void
    {
        Storage::fake('private');

        $urgencyValues = ['rendah', 'sedang', 'tinggi', 'darurat'];
        $counterexamples = [];

        foreach ($urgencyValues as $urgencyValue) {
            $class = SchoolClass::firstOrFail();

            // Submit damage report with specific urgency value
            $response = $this->withSession([
                'math_captcha_answer' => 8,
                'report_submit_token' => Str::uuid()->toString()
            ])
            ->post(route('public.report.store'), [
                'reporter_type' => 'siswa',
                'reporter_name' => "Pelapor Urgency {$urgencyValue}",
                'reporter_phone' => '+62812345678' . random_int(10, 99),
                'reporter_class_id' => $class->id,
                'report_type' => 'damage',
                'title' => "Kerusakan - Urgency {$urgencyValue}",
                'incident_date' => now()->toDateString(),
                'description' => "Test damage report dengan urgency {$urgencyValue}.",
                'urgency' => $urgencyValue,
                'item_name' => "Item Test {$urgencyValue}",
                'damage_condition' => "Kondisi kerusakan untuk {$urgencyValue}.",
                'consent' => '1',
                'captcha' => '8',
            ]);

            $response->assertRedirect();

            // Query database to verify the bug
            $report = Report::query()
                ->where('title', "Kerusakan - Urgency {$urgencyValue}")
                ->firstOrFail();

            $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

            // BUG VERIFICATION:
            // Expected behavior (after fix): damage_detail.priority = NULL
            // Actual behavior (unfixed code): damage_detail.priority = urgency_value
            
            // This assertion will FAIL on unfixed code, confirming the bug exists
            $this->assertNull(
                $damageDetail->priority,
                "PRIORITY PERSISTENCE BUG: Priority should be NULL initially, but was set to '{$damageDetail->priority}' from urgency '{$urgencyValue}'. This proves priority is incorrectly mirrored from urgency instead of being independent."
            );

            // Document counterexample showing the bug
            $counterexamples[] = [
                'urgency_value' => $urgencyValue,
                'priority_value' => $damageDetail->priority,
                'report_id' => $report->id,
                'should_be_null' => true,
            ];
        }

        // Log all counterexamples for analysis
        info('Priority Persistence Bug Counterexamples:', $counterexamples);
    }

    /**
     * Property: Bug Condition - Verify urgency is correctly set (independent of priority)
     * 
     * While priority should be NULL, urgency should be correctly set from user input.
     * This verifies the data is being submitted correctly.
     */
    public function test_damage_report_urgency_correctly_set_independent_of_priority(): void
    {
        Storage::fake('private');

        $class = SchoolClass::firstOrFail();
        $urgencyValue = 'darurat';

        $response = $this->withSession([
            'math_captcha_answer' => 9,
            'report_submit_token' => Str::uuid()->toString()
        ])
        ->post(route('public.report.store'), [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Pelapor Darurat Test',
            'reporter_phone' => '+62812345678901',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Kerusakan - Urgency Darurat Test',
            'incident_date' => now()->toDateString(),
            'description' => 'Test damage report dengan urgency darurat.',
            'urgency' => $urgencyValue,
            'item_name' => 'Item Test Darurat',
            'damage_condition' => 'Kondisi kerusakan darurat.',
            'consent' => '1',
            'captcha' => '9',
        ]);

        $response->assertRedirect();

        $report = Report::query()
            ->where('title', 'Kerusakan - Urgency Darurat Test')
            ->firstOrFail();

        $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

        // Verify urgency is correctly set (this should pass)
        $this->assertSame($urgencyValue, $report->urgency, 
            "Urgency should be correctly set to '{$urgencyValue}' from user input.");

        // Document that urgency is set correctly while priority exhibits the bug
        info('Priority vs Urgency Comparison:', [
            'report_urgency' => $report->urgency,
            'damage_detail_priority' => $damageDetail->priority,
            'bug_condition' => $damageDetail->priority === $report->urgency,
            'message' => 'BUG: Priority mirrors urgency value instead of being independent (NULL)',
        ]);
    }

    /**
     * Property: Bug Condition - All damage reports show the bug consistently
     * 
     * This test verifies that the bug appears consistently across different scenarios.
     */
    public function test_priority_bug_affects_all_damage_report_types(): void
    {
        Storage::fake('private');

        $class = SchoolClass::firstOrFail();
        $testScenarios = [
            ['urgency' => 'rendah', 'reporter' => 'siswa'],
            ['urgency' => 'sedang', 'reporter' => 'guru'],
            ['urgency' => 'tinggi', 'reporter' => 'staff'],
        ];

        foreach ($testScenarios as $scenario) {
            $reporterKey = $scenario['reporter'] === 'siswa' 
                ? 'reporter_class_id' 
                : ($scenario['reporter'] === 'guru' ? 'reporter_subject_id' : 'reporter_staff_unit_id');

            $reporterValue = match($scenario['reporter']) {
                'siswa' => SchoolClass::firstOrFail()->id,
                'guru' => \App\Models\Subject::where('is_active', true)->firstOrFail()->id,
                'staff' => \App\Models\StaffUnit::where('is_active', true)->firstOrFail()->id,
            };

            $postData = [
                'reporter_type' => $scenario['reporter'],
                'reporter_name' => "Pelapor {$scenario['reporter']} {$scenario['urgency']}",
                'reporter_phone' => '+62812345678' . random_int(1000, 9999),
                'report_type' => 'damage',
                'title' => "Kerusakan - {$scenario['urgency']}",
                'incident_date' => now()->toDateString(),
                'description' => 'Test damage report.',
                'urgency' => $scenario['urgency'],
                'item_name' => 'Item Test',
                'damage_condition' => 'Kondisi kerusakan.',
                'consent' => '1',
                'captcha' => '8',
            ];

            $postData[$reporterKey] = $reporterValue;

            $response = $this->withSession([
                'math_captcha_answer' => 8,
                'report_submit_token' => Str::uuid()->toString()
            ])
            ->post(route('public.report.store'), $postData);

            $response->assertRedirect();

            $report = Report::query()
                ->where('title', "Kerusakan - {$scenario['urgency']}")
                ->latest()
                ->firstOrFail();

            $damageDetail = DamageDetail::where('report_id', $report->id)->firstOrFail();

            // This assertion will FAIL, confirming bug affects all report types
            $this->assertNull(
                $damageDetail->priority,
                "BUG CONDITION: Priority should be NULL for {$scenario['reporter']} with urgency={$scenario['urgency']}, but was '{$damageDetail->priority}'"
            );
        }
    }
}
