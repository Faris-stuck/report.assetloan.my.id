<?php

namespace Tests\Feature;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFormAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_every_public_report_control_has_an_accessible_label(): void
    {
        $response = $this->get('/')->assertOk();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $controls = $xpath->query("//form[@id='form-laporan']//input[not(@type='hidden') and not(@type='submit') and not(@type='button')] | //form[@id='form-laporan']//select | //form[@id='form-laporan']//textarea");

        $this->assertNotFalse($controls);
        $this->assertGreaterThan(0, $controls->length);

        foreach ($controls as $control) {
            $this->assertInstanceOf(DOMElement::class, $control);
            $name = $control->getAttribute('name') ?: $control->getAttribute('id') ?: $control->tagName;
            $id = $control->getAttribute('id');
            $hasExplicitLabel = $id !== '' && $xpath->query("//label[@for='{$id}']")->length > 0;
            $hasWrappingLabel = $xpath->query('ancestor::label', $control)->length > 0;
            $hasAriaLabel = trim($control->getAttribute('aria-label')) !== '' || trim($control->getAttribute('aria-labelledby')) !== '';

            $this->assertTrue(
                $hasExplicitLabel || $hasWrappingLabel || $hasAriaLabel,
                "Form control '{$name}' needs a <label>, aria-label, or aria-labelledby. Placeholder text is not an accessible label."
            );
        }
    }
}
