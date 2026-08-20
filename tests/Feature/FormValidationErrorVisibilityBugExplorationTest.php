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

        // Visibility dikelola JS inline murni (class d-none di #step-error-alert),
        // tanpa attribute x-show — konsisten dengan penghapusan mekanisme hide berbasis CSS/JS.
        $this->assertStringContainsString(
            'data-step-error-text',
            $content
        );
        $this->assertStringNotContainsString(
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
            'getFieldLabel(f)',
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

    public function test_submit_button_has_accessible_touch_target(): void
    {
        $content = file_get_contents(
            resource_path('views/public/report-form.blade.php')
        );

        $this->assertStringContainsString(
            'min-height: 44px',
            $content
        );

        $this->assertStringContainsString(
            'aria-label="Kirim laporan"',
            $content
        );
    }

    public function test_validation_error_scrolls_into_view(): void
    {
        $content = file_get_contents(
            resource_path('views/public/report-form.blade.php')
        );

        $this->assertStringContainsString(
            'alert.scrollIntoView',
            $content
        );
    }
}
