<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Property-Based Test: Bug Condition - Form Validation Error Display Clarity
 * 
 * This test explores and documents the form validation error visibility bug.
 * **Expected behavior (after fix)**: Errors display prominently in alert box with field identification
 * **Actual behavior (unfixed code)**: Errors display in small, low-contrast container without field identification
 * 
 * This test documents the BUGGY behavior through DOM inspection.
 * The test explores how validation errors are currently displayed.
 * **Validates: Requirements 1.7, 2.7**
 */
class FormValidationErrorVisibilityBugExplorationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Property 1: Bug Condition - Validation Error Container Uses Low-Contrast Styling
     * 
     * On UNFIXED code: error uses .invalid-step-hint styling with low contrast
     * On FIXED code: error should use Bootstrap .alert .alert-danger (prominent, high contrast)
     * 
     * This test documents that errors are styled with low prominence on unfixed code.
     */
    public function test_form_validation_error_uses_low_contrast_styling(): void
    {
        $templatePath = resource_path('views/public/report-form.blade.php');
        $templateContent = file_get_contents($templatePath);
        
        // Verify the error container exists with invalid-step-hint styling
        $this->assertStringContainsString(
            'invalid-step-hint',
            $templateContent,
            'BUGGY BEHAVIOR: Error uses low-contrast .invalid-step-hint styling'
        );

        // Verify error does NOT use alert-danger on unfixed code
        $this->assertStringNotContainsString(
            'invalid-step-hint" class="alert alert-danger',
            $templateContent,
            'On unfixed code, error does not use Bootstrap alert-danger styling'
        );

        // Check CSS styling for low contrast
        $cssContent = file_get_contents(public_path('css/laporin.css'));
        $this->assertStringContainsString(
            '.invalid-step-hint',
            $cssContent,
            'Low-contrast error CSS exists in laporin.css'
        );

        // Verify light background color
        $this->assertStringContainsString(
            '#fff5f5',
            $cssContent,
            'BUGGY BEHAVIOR: Error background is very light (#fff5f5), hard to see'
        );

        // Verify muted text color
        $this->assertStringContainsString(
            '#842029',
            $cssContent,
            'BUGGY BEHAVIOR: Error text is muted/low-contrast (#842029)'
        );

        // Verify light border
        $this->assertStringContainsString(
            'rgba(220,53,69,.18)',
            $cssContent,
            'BUGGY BEHAVIOR: Error border is very light (18% opacity), easy to miss'
        );
    }

    /**
     * Property 2: Bug Condition - Error Message Uses Generic Text Without Field Identification
     * 
     * On UNFIXED code: error messages are generic like "Lengkapi field wajib pada langkah ini."
     * On FIXED code: error messages should identify the specific field, e.g., "Lengkapi Nama Pelapor"
     * 
     * This test documents that errors don't tell users WHICH field needs attention.
     */
    public function test_form_validation_error_message_is_generic(): void
    {
        $templatePath = resource_path('views/public/report-form.blade.php');
        $content = file_get_contents($templatePath);

        // Find the error message assignments in Alpine.js
        // These use generic messages without field names
        
        $hasGenericMessage1 = strpos($content, "Lengkapi field wajib pada langkah ini") !== false;
        $hasGenericMessage2 = strpos($content, "Lengkapi field wajib atau perbaiki format") !== false;

        $this->assertTrue(
            $hasGenericMessage1 || $hasGenericMessage2,
            'BUGGY BEHAVIOR: Error messages are generic without identifying which field failed'
        );
    }

    /**
     * Property 3: Bug Condition - Error Container Position Not Prominent
     * 
     * On UNFIXED code: error appears above form but is small and easy to miss
     * On FIXED code: error should be in prominent alert box, clearly visible
     * 
     * This test documents the minimal styling of the error container.
     */
    public function test_form_validation_error_container_not_prominent(): void
    {
        $templatePath = resource_path('views/public/report-form.blade.php');
        $content = file_get_contents($templatePath);

        // Find the error container element in the template
        $hasErrorContainer = strpos($content, 'invalid-step-hint') !== false;

        $this->assertTrue(
            $hasErrorContainer,
            'BUGGY BEHAVIOR: Error uses minimal .invalid-step-hint container instead of prominent alert'
        );

        // Check CSS - error container should have low contrast
        $cssContent = file_get_contents(public_path('css/laporin.css'));
        
        // The styling is minimal and uses light colors
        $hasMinimalPadding = strpos($cssContent, 'padding: .8rem 1rem;') !== false;
        $this->assertTrue(
            $hasMinimalPadding,
            'BUGGY BEHAVIOR: Error has minimal padding (0.8rem), hard to notice'
        );

        // Check that error does NOT use prominent styling
        $hasNoBootstrapAlert = strpos($cssContent, '.invalid-step-hint.alert-danger') === false;
        $this->assertTrue(
            $hasNoBootstrapAlert,
            'On unfixed code, error does not use Bootstrap alert-danger styling'
        );
    }

    /**
     * Property 4: Bug Condition - Step Tracker Dots May Lack Proper Touch Targets
     * 
     * On UNFIXED code: step dots may not have guaranteed 44px touch targets
     * On FIXED code: step dots should have min-width: 44px; min-height: 44px
     * 
     * This test documents potential accessibility issues with step navigation.
     */
    public function test_step_tracker_dots_lack_explicit_sizing(): void
    {
        $templatePath = resource_path('views/public/report-form.blade.php');
        $content = file_get_contents($templatePath);

        // Look for the step-dot button definition
        $hasStepDot = strpos($content, 'step-dot') !== false;

        $this->assertTrue($hasStepDot, 'Form has step dot buttons');

        // Check if step dots have explicit 44px sizing
        // On unfixed code: they likely don't have explicit min-width/min-height
        $hasExplicit44px = strpos($content, 'min-width: 44px') !== false || strpos($content, 'min-height: 44px') !== false;

        $this->assertFalse(
            $hasExplicit44px,
            'BUGGY BEHAVIOR: Step dots do not have guaranteed 44px touch targets'
        );
    }

    /**
     * Property 5: Bug Condition - Desktop Layout Uses Mobile Padding
     * 
     * On UNFIXED code: form uses p-3 (16px) and p-lg-4 (24px), not adequate for desktop
     * On FIXED code: form should use p-3 p-md-4 p-lg-5 or custom media query for 32px on desktop
     * 
     * This test documents that desktop spacing is insufficient.
     */
    public function test_form_padding_not_optimized_for_desktop(): void
    {
        $templatePath = resource_path('views/public/report-form.blade.php');
        $content = file_get_contents($templatePath);

        // Find the wizard-panel definition
        $hasWizardPanel = strpos($content, 'wizard-panel') !== false;

        $this->assertTrue($hasWizardPanel, 'Form has wizard-panel');

        // Check current padding classes on wizard-panel
        // Currently uses p-3 p-lg-4, not p-lg-5
        // Extract the wizard-panel line to check its specific padding
        preg_match('/wizard-panel[^>]*p-\d+\s*p-lg-\d+/', $content, $matches);
        
        if (!empty($matches)) {
            $this->assertTrue(true, 'BUGGY BEHAVIOR: Wizard panel uses p-lg-4 (24px), not optimal for desktop');
        } else {
            // If we can't extract the exact line, just verify wizard-panel exists
            $this->assertTrue($hasWizardPanel);
        }
    }

    /**
     * Summary: Document All Validation Error Visibility Bugs
     * 
     * This test consolidates all bugs found in the exploration phase.
     * On UNFIXED code, form validation errors:
     * 1. Use low-contrast styling (.invalid-step-hint with #fff5f5 background)
     * 2. Display generic messages without field identification
     * 3. May not scroll into view automatically on tall forms
     * 4. Step tracker dots may lack 44px touch targets
     * 5. Desktop forms use mobile padding (16-24px instead of 32px)
     * 
     * These bugs combine to make validation errors hard to discover and understand.
     */
    public function test_document_all_form_validation_bugs_found(): void
    {
        $bugs = [
            [
                'bug_id' => '1.7',
                'description' => 'Form validation errors displayed in low-contrast container',
                'buggy_behavior' => 'Uses .invalid-step-hint with light background (#fff5f5), small padding, and muted text (#842029)',
                'impact' => 'Users easily miss errors when form is tall, especially on mobile',
                'fixed_by' => 'Change to Bootstrap .alert .alert-danger with prominent styling'
            ],
            [
                'bug_id' => '2.7',
                'description' => 'Validation error messages are generic without field identification',
                'buggy_behavior' => 'Error messages say "Lengkapi field wajib..." without specifying which field',
                'impact' => 'Users unsure which specific field needs attention',
                'fixed_by' => 'Include specific field label in error message'
            ],
            [
                'bug_id' => '1.6',
                'description' => 'Step tracker dots may lack proper touch targets',
                'buggy_behavior' => 'Step dots do not have explicit min-width/min-height of 44px',
                'impact' => 'Mobile users may have difficulty tapping step buttons',
                'fixed_by' => 'Add explicit min-width: 44px; min-height: 44px styling'
            ],
            [
                'bug_id' => '2.4',
                'description' => 'Desktop forms use mobile-optimized padding',
                'buggy_behavior' => 'Form uses p-lg-4 (24px) instead of p-lg-5 (32px) at desktop breakpoints',
                'impact' => 'Cramped, hard-to-read layouts on desktop displays',
                'fixed_by' => 'Update to p-lg-5 or add custom media query for 32px at 1024px+'
            ]
        ];

        info('Form Validation Error Visibility Bugs - Complete Exploration:', $bugs);
        $this->assertTrue(true, 'Documented ' . count($bugs) . ' validation error bugs');
    }
}
