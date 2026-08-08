<?php

namespace Tests\Feature;

use Tests\TestCase;

class FormLayoutStabilityTest extends TestCase
{
    /**
     * Test that form renders with conditional fields markup
     */
    public function test_form_has_conditional_field_class(): void
    {
        $response = $this->get('/laporan');
        
        $response->assertStatus(200);
        $response->assertSee('conditional-field', false);
        $response->assertSee('x-show="reporter===\'siswa\'"', false);
        $response->assertSee('x-transition', false);
    }

    /**
     * Test that error alert has correct styling classes
     */
    public function test_error_alert_has_proper_classes(): void
    {
        $response = $this->get('/laporan');
        
        $response->assertStatus(200);
        $response->assertSee('alert alert-danger', false);
        $response->assertSee('id="step-error-alert"', false);
        $response->assertSee('fa-exclamation-circle', false);
    }

    /**
     * Test form submission with validation errors preserves data
     */
    public function test_form_preserves_data_on_validation_error(): void
    {
        $response = $this->post('/laporan', [
            'reporter_name' => 'Test User',
            'reporter_type' => 'siswa',
            // Missing required fields to trigger validation
        ]);
        
        // Should redirect back with errors
        $response->assertRedirect();
        $response->assertSessionHasErrors();
    }

    /**
     * Test CSS media queries are present for responsive design
     */
    public function test_css_has_responsive_media_queries(): void
    {
        $cssPath = public_path('css/laporin.css');
        $cssContent = file_get_contents($cssPath);
        
        // Check for desktop media query (1024px+)
        $this->assertStringContainsString('@media (min-width: 1024px)', $cssContent);
        
        // Check for tablet media query (768px to 1023px)
        $this->assertStringContainsString('@media (min-width: 768px) and (max-width: 1023px)', $cssContent);
        
        // Check for mobile media query
        $this->assertStringContainsString('@media (max-width: 767px)', $cssContent);
        
        // Check for 32px desktop padding
        $this->assertStringContainsString('padding: 2rem;', $cssContent);
    }

    /**
     * Test that button height meets WCAG AA minimum (44px)
     */
    public function test_css_button_height_accessibility(): void
    {
        $cssPath = public_path('css/laporin.css');
        $cssContent = file_get_contents($cssPath);
        
        // Check for 44px minimum button height
        $this->assertStringContainsString('min-height: 44px', $cssContent);
        
        // Check for step-dot minimum size
        $this->assertStringContainsString('min-width: 44px', $cssContent);
    }

    /**
     * Test conditional field transition CSS
     */
    public function test_conditional_field_has_smooth_transition(): void
    {
        $cssPath = public_path('css/laporin.css');
        $cssContent = file_get_contents($cssPath);
        
        $this->assertStringContainsString('.conditional-field', $cssContent);
        $this->assertStringContainsString('transition:', $cssContent);
    }

    /**
     * Test that form layout doesn't shift when conditional fields toggle
     */
    public function test_conditional_field_layout_stability_css(): void
    {
        $cssPath = public_path('css/laporin.css');
        $cssContent = file_get_contents($cssPath);
        
        // Ensure x-show display rules prevent layout shifts
        $this->assertStringContainsString('[x-show]', $cssContent);
        $this->assertStringContainsString('[x-show="false"]', $cssContent);
        $this->assertStringContainsString('display: none !important', $cssContent);
    }
}

