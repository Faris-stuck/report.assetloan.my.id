<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassMajorGroupingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_every_public_class_selector_is_grouped_by_major_and_naturally_sorted(): void
    {
        $response = $this->get('/')->assertOk();

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($response->getContent());
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $expectedGroups = [
            'RPL — Rekayasa Perangkat Lunak',
            'TKR — Teknik Kendaraan Ringan',
            'TITL — Teknik Instalasi Tenaga Listrik',
            'TAV — Teknik Elektronika Audio Video',
        ];

        foreach (['reporter_class_id', 'related_class_id'] as $field) {
            $groups = $xpath->query("//select[@name='{$field}']/optgroup");

            $this->assertNotFalse($groups);
            $this->assertSame(4, $groups->length, "{$field} should expose one option group per major.");
            $this->assertSame(
                $expectedGroups,
                array_map(static fn ($group) => $group->getAttribute('label'), iterator_to_array($groups)),
                "{$field} should keep the configured major order."
            );

            $rplOptions = $xpath->query("//select[@name='{$field}']/optgroup[@label='RPL — Rekayasa Perangkat Lunak']/option");
            $this->assertNotFalse($rplOptions);
            $this->assertSame(30, $rplOptions->length);
            $this->assertSame(
                array_map(static fn (int $number) => "Kelas 10 RPL {$number}", range(1, 10)),
                array_map(static fn ($option) => trim($option->textContent), array_slice(iterator_to_array($rplOptions), 0, 10)),
                "{$field} should sort class numbers naturally (1, 2, …, 10), not lexicographically (1, 10, 2)."
            );
        }
    }
}
