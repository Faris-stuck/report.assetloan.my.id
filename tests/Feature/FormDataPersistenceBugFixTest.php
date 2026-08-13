<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormDataPersistenceBugFixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the report form view renders successfully
     * with Alpine.js data persistence initialized
     */
    public function test_public_report_form_renders_with_data_persistence()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        $response->assertViewHas('locations');
        $response->assertViewHas('subjects');
        $response->assertViewHas('staffUnits');
        
        // Check for Alpine.js form state initialization
        $response->assertSee('formData', false);
        $response->assertSee('saveFormState', false);
        $response->assertSee('clearFormState', false);
        $response->assertSee('sessionStorage', false);
    }

    /**
     * Test that form has 4-step structure with proper data models
     */
    public function test_form_has_four_steps_with_data_models()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Check for step markers in HTML
        $response->assertSee('data-step="1"', false);
        $response->assertSee('data-step="2"', false);
        $response->assertSee('data-step="3"', false);
        $response->assertSee('data-step="4"', false);
        
        // Check for form field bindings to formData
        $response->assertSee('x-model="formData.step1', false);
        $response->assertSee('x-model="formData.step2', false);
        $response->assertSee('x-model="formData.step3', false);
        $response->assertSee('x-model="formData.step4', false);
    }

    /**
     * Test that form has single-page submit flow with data preservation
     */
    public function test_form_has_step_navigation_buttons()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Wizard bertahap (JS inline murni): "Lanjut" memvalidasi langkah berjalan lalu
        // memunculkan langkah berikutnya; "Kembali" untuk mengulang langkah sebelumnya.
        $response->assertSee('LaporinWizard', false);
        $response->assertSee('data-wizard-action="next"', false);
        $response->assertSee('data-wizard-action="prev"', false);
        $response->assertSee('saveFormState', false);  // data persistence (Alpine)
    }

    /**
     * Test that error handling preserves form data
     */
    public function test_validation_error_preserves_form_data()
    {
        $response = $this->get(route('public.report'));
        
        // Verify the form includes error display logic (JS murni: data-step-error-text)
        $response->assertSee('stepError', false);
        $response->assertSee('data-step-error-text', false);
        $response->assertSee('alert alert-danger', false);  // Error display
    }

    /**
     * Test that form has sessionStorage integration for data persistence
     */
    public function test_form_includes_localstorage_integration()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Check for sessionStorage methods
        $response->assertSee("sessionStorage.getItem('reportFormData')", false);
        $response->assertSee("sessionStorage.setItem('reportFormData'", false);
        $response->assertSee("sessionStorage.removeItem('reportFormData')", false);
    }

    /**
     * Test that form shows single-page guidance text
     */
    public function test_form_has_step_hints()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Teks panduan wizard langkah-demi-langkah (JS murni)
        $response->assertSee('data-step-hint', false);
        $response->assertSee('Kirim Laporan', false);
    }

    /**
     * Test that form clears sessionStorage on successful submission
     */
    public function test_form_clears_localstorage_on_submit()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Check submit button calls clearFormState
        $response->assertSee('Kirim Laporan', false);
        $response->assertSee('clearFormState', false);
    }

    /**
     * Test that conditional fields are properly bound with x-model
     */
    public function test_conditional_fields_have_proper_data_binding()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Check for conditional fields data-reporter-role attributes
        $response->assertSee('data-reporter-role="siswa"', false);
        $response->assertSee('data-reporter-role="guru"', false);
        $response->assertSee('data-reporter-role="staff"', false);
        $response->assertSee(':disabled="reporter!==\'siswa\'"', false);
    }

    /**
     * Test that reporter_type change syncs between component state and formData
     */
    public function test_reporter_type_syncs_component_state_and_formdata()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Check that reporter_type is bound to both formData and reactive state
        $response->assertSee('x-model="formData.step1.reporter_type"', false);
        $response->assertSee('@change="reporter=formData.step1.reporter_type', false);
    }

    /**
     * Test that report_type change syncs between component state and formData
     */
    public function test_report_type_syncs_component_state_and_formdata()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Check that report_type is bound to formData
        $response->assertSee('x-model="formData.step2.report_type"', false);
        // Check that type component state is updated when formData changes
        $response->assertSee('@change="type=formData.step2.report_type', false);
    }

    /**
     * Test that all step 1 fields are bound to formData
     */
    public function test_step1_all_fields_have_formdata_binding()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Step 1 fields should be bound to formData.step1
        $response->assertSee('x-model="formData.step1.reporter_name"', false);
        $response->assertSee('x-model="formData.step1.reporter_class_id"', false);
        $response->assertSee('x-model="formData.step1.reporter_absence_number"', false);
        $response->assertSee('x-model="formData.step1.reporter_subject_id"', false);
        $response->assertSee('x-model="formData.step1.reporter_staff_unit_id"', false);
        $response->assertSee('x-model="formData.step1.reporter_phone"', false);
        $response->assertSee('x-model="formData.step1.reporter_email"', false);
    }

    /**
     * Test that all step 3 fields are bound to formData
     */
    public function test_step3_all_fields_have_formdata_binding()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Step 3 fields should be bound to formData.step3
        $response->assertSee('x-model="formData.step3.title"', false);
        $response->assertSee('x-model="formData.step3.urgency"', false);
        $response->assertSee('x-model="formData.step3.related_class_id"', false);
        $response->assertSee('x-model="formData.step3.alleged_actor_name"', false);
        $response->assertSee('x-model="formData.step3.description"', false);
        $response->assertSee('x-model="formData.step3.item_name"', false);
        $response->assertSee('x-model="formData.step3.location_id"', false);
        $response->assertSee('x-model="formData.step3.damage_condition"', false);
    }

    /**
     * Test that all step 4 fields are bound to formData
     */
    public function test_step4_all_fields_have_formdata_binding()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Step 4 fields should be bound to formData.step4
        $response->assertSee('x-model="formData.step4.consent"', false);
        $response->assertSee('x-model="formData.step4.captcha"', false);
    }

    /**
     * Test that form has proper height/44px minimum for buttons (touch targets)
     */
    public function test_buttons_have_minimum_44px_height_for_accessibility()
    {
        $response = $this->get(route('public.report'));
        
        $response->assertStatus(200);
        
        // Check for 44px minimum height / touch target on the submit button
        $response->assertSee('min-height: 44px', false);
        $response->assertSee('aria-label="Kirim laporan"', false);
    }
}
