<?php

namespace Tests\Feature;

use App\Models\DamageDetail;
use App\Models\Report;
use App\Models\Location;
use App\Models\SchoolClass;
use App\Services\PublicReport\PublicReportService;
use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Property-Based Test: Bug Condition - Priority Data Persistence Defect
 * 
 * This test explores and documents the priority persistence bug by directly calling
 * PublicReportService::create() with various urgency values.
 * 
 * **Expected behavior (after fix)**: damage_detail.priority should be NULL
 * **Actual behavior (unfixed code)**: damage_detail.priority equals the urgency value
 * 
 * This test demonstrates the bug with counterexamples.
 * **Validates: Requirements 1.2, 2.2**
 */
class PublicReportPriorityBugExplorationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Test that directly invokes PublicReportService with mock Request
     * and demonstrates the priority bug with counterexamples for each urgency value
     */
    public function test_service_creates_damage_report_with_priority_mirroring_urgency(): void
    {
        // Setup required models
        $class = SchoolClass::create([
            'class_name' => 'Test Class',
            'grade_level' => '10',
            'academic_year' => '2024/2025',
        ]);
        $location = Location::create([
            'location_name' => 'Test Location',
            'location_type' => 'classroom',
        ]);

        // Property-based test: generate all urgency values
        $urgencyValues = ['rendah', 'sedang', 'tinggi', 'darurat'];
        $counterexamples = [];

        foreach ($urgencyValues as $urgencyValue) {
            // Create a mock request
            $request = new Request();
            $request->setMethod('POST');

            // Validated data from public form (step 3)
            // NOTE: 'priority' is NOT in validated data (it's admin-only field)
            $validated = [
                'reporter_type' => 'siswa',
                'reporter_name' => "Test Reporter {$urgencyValue}",
                'reporter_class_id' => $class->id,
                'report_type' => 'damage',
                'title' => "Test Damage - {$urgencyValue}",
                'location_id' => $location->id,
                'incident_date' => now()->toDateString(),
                'description' => "Test description for urgency {$urgencyValue}",
                'urgency' => $urgencyValue,
                'item_name' => "Item {$urgencyValue}",
                'damage_condition' => 'Rusak',
                'consent' => '1',
                'captcha' => '8',
            ];

            // Create report using PublicReportService
            $service = new PublicReportService();
            [$report, $accessCode, $notificationSent] = $service->create($request, $validated);

            // Query the created DamageDetail from database
            $damageDetail = DamageDetail::where('report_id', $report->id)->sole();

            // COUNTEREXAMPLE: Document what the unfixed code actually does
            $counterexamples[] = [
                'urgency_submitted' => $urgencyValue,
                'priority_in_database' => $damageDetail->priority,
                'bug_present' => $damageDetail->priority === $urgencyValue,
                'expected_priority' => null,
                'report_id' => $report->id,
            ];

            // This will FAIL on unfixed code, proving the bug exists
            $this->assertNull(
                $damageDetail->priority,
                "PRIORITY PERSISTENCE BUG DETECTED: " .
                "Expected priority to be NULL (independent), " .
                "but priority was set to '{$damageDetail->priority}' " .
                "which mirrors urgency value '{$urgencyValue}'. " .
                "This violates field independence and prevents independent priority management."
            );
        }

        // Document all counterexamples
        info('Priority Persistence Bug Counterexamples (Actual Behavior - UNFIXED CODE):', 
            [
                'description' => 'These counterexamples show priority incorrectly mirroring urgency value',
                'counterexamples' => $counterexamples,
                'all_show_bug' => array_all($counterexamples, fn($ex) => $ex['bug_present']),
            ]
        );
    }

    /**
     * Verify urgency is correctly set (independent of priority)
     * This shows the data submission path works, but the priority assignment is wrong
     */
    public function test_urgency_correctly_set_while_priority_exhibits_bug(): void
    {
        $class = SchoolClass::create([
            'class_name' => 'Test Class',
            'grade_level' => '10',
            'academic_year' => '2024/2025',
        ]);
        $location = Location::create([
            'location_name' => 'Test Location',
            'location_type' => 'classroom',
        ]);
        $urgencyValue = 'darurat';

        $request = new Request();
        $request->setMethod('POST');

        $validated = [
            'reporter_type' => 'siswa',
            'reporter_name' => 'Test Reporter Darurat',
            'reporter_class_id' => $class->id,
            'report_type' => 'damage',
            'title' => 'Test Damage Darurat',
            'location_id' => $location->id,
            'incident_date' => now()->toDateString(),
            'description' => 'Test description for urgency darurat',
            'urgency' => $urgencyValue,
            'item_name' => 'Item Darurat',
            'damage_condition' => 'Rusak parah',
            'consent' => '1',
            'captcha' => '8',
        ];

        $service = new PublicReportService();
        [$report, $accessCode, $notificationSent] = $service->create($request, $validated);

        $damageDetail = DamageDetail::where('report_id', $report->id)->sole();

        // Verify urgency is correctly set
        $this->assertSame($urgencyValue, $report->urgency,
            "Urgency correctly set to '{$urgencyValue}' from user input"
        );
        // Fixed behavior: urgency tetap tersimpan pada report,
        // sedangkan priority awal harus NULL dan dikelola Sarpras.
        $this->assertNull(
            $damageDetail->priority,
            'Priority awal harus NULL dan tidak boleh menyalin urgency.'
        );

        // Log the comparison
        info('Priority vs Urgency Comparison (Bug Demonstration):', [
            'report_urgency' => $report->urgency,
            'damage_detail_priority' => $damageDetail->priority,
            'fields_mirrored' => $damageDetail->priority === $report->urgency,
            'bug_message' => 'Priority incorrectly mirrors urgency instead of being independent',
        ]);
    }
}

/**
 * Helper: Check if all array elements match condition
 */
if (!function_exists('array_all')) {
    function array_all(array $array, callable $callback): bool {
        foreach ($array as $item) {
            if (!$callback($item)) {
                return false;
            }
        }
        return true;
    }
}
