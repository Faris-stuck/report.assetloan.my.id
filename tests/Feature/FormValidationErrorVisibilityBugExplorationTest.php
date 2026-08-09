<?php

namespace Tests\Feature;

use Tests\TestCase;

class FormValidationErrorVisibilityBugExplorationTest extends TestCase
{
    public function test_validation_error_container_is_prominent(): void
    {
        $content = file_get_contents(
            resource_path('views/public/report-form.blade.php')
        );

        $this->assertStringContainsString(
            'class="alert alert-danger',
            $content
        );

        $this->assertStringContainsString(
            'id="step-error-alert"',
            $content
        );

        $this->assertStringContainsString(
            'x-show="stepError"',
            $content
        );
    }

    public function test_validation_message_identifies_invalid_field(): void
    {
        $content = file_get_contents(
            resource_path('views/public/report-form.blade.php')
        );

        $this->assertStringContainsString(
            'fieldLabel(input)',
            $content
        );

        $this->assertStringContainsString(
            'this.fieldLabel(firstInvalid)',
            $content
        );

        $this->assertStringNotContainsString(
            "this.stepError = 'Lengkapi field wajib pada langkah ini.';",
            $content
        );

        $this->assertStringNotContainsString(
            "this.stepError = 'Lengkapi field wajib atau perbaiki format.';",
            $content
        );
    }

    public function test_step_tracker_has_accessible_touch_targets(): void
    {
        $content = file_get_contents(
            resource_path('views/public/report-form.blade.php')
        );

        $this->assertStringContainsString(
            'min-width: 44px',
            $content
        );

        $this->assertStringContainsString(
            'min-height: 44px',
            $content
        );
    }

    public function test_validation_error_scrolls_into_view(): void
    {
        $content = file_get_contents(
            resource_path('views/public/report-form.blade.php')
        );

        $this->assertStringContainsString(
            'errorAlert.scrollIntoView',
            $content
        );
    }
}
