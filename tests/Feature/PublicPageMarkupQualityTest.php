<?php

namespace Tests\Feature;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPageMarkupQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_pages_have_valid_landmarks_unique_ids_and_named_actions(): void
    {
        $paths = [
            '/',
            '/lapor',
            route('seo.bullying-guide', absolute: false),
            route('seo.faq', absolute: false),
            route('track.form', absolute: false),
            route('login', absolute: false),
        ];

        foreach ($paths as $path) {
            $response = $this->get($path)->assertOk();
            [$dom, $xpath] = $this->dom($response->getContent());

            $this->assertSame(1, $xpath->query('//main')->length, "{$path} must contain exactly one main landmark.");
            $this->assertSame(1, $xpath->query('//h1')->length, "{$path} must contain exactly one h1.");

            $ids = [];
            foreach ($xpath->query('//*[@id]') as $element) {
                $this->assertInstanceOf(DOMElement::class, $element);
                $id = trim($element->getAttribute('id'));
                $this->assertNotSame('', $id, "{$path} contains an empty id.");
                $this->assertArrayNotHasKey($id, $ids, "{$path} contains duplicate id '{$id}'.");
                $ids[$id] = true;
            }

            foreach ($xpath->query('//a | //button') as $action) {
                $this->assertInstanceOf(DOMElement::class, $action);
                $accessibleName = trim(preg_replace('/\s+/', ' ', $action->textContent));
                $accessibleName .= trim($action->getAttribute('aria-label'));
                $accessibleName .= trim($action->getAttribute('title'));
                $this->assertNotSame('', $accessibleName, "{$path} contains a link/button without an accessible name.");

                if ($action->tagName === 'a') {
                    $href = trim($action->getAttribute('href'));
                    $this->assertNotSame('', $href, "{$path} contains a link with empty href.");
                    $this->assertNotSame('#', $href, "{$path} contains a placeholder href='#'.");
                }
            }
        }
    }

    public function test_every_public_internal_link_resolves_without_a_404_or_server_error(): void
    {
        $sourcePaths = [
            '/',
            route('seo.bullying-guide', absolute: false),
            route('seo.faq', absolute: false),
            route('track.form', absolute: false),
            route('login', absolute: false),
        ];
        $internalPaths = [];

        foreach ($sourcePaths as $sourcePath) {
            [, $xpath] = $this->dom($this->get($sourcePath)->assertOk()->getContent());
            foreach ($xpath->query('//a[@href]') as $link) {
                $this->assertInstanceOf(DOMElement::class, $link);
                $href = trim($link->getAttribute('href'));
                if ($href === '' || str_starts_with($href, '#')) {
                    continue;
                }

                $parts = parse_url($href);
                if ($parts === false || (isset($parts['host']) && $parts['host'] !== parse_url(config('app.url'), PHP_URL_HOST))) {
                    continue;
                }

                $path = $parts['path'] ?? '/';
                if ($path !== '/logout') {
                    $internalPaths[$path] = true;
                }
            }
        }

        $this->assertNotEmpty($internalPaths);
        foreach (array_keys($internalPaths) as $path) {
            $response = $this->get($path);
            $this->assertLessThan(400, $response->getStatusCode(), "Internal link {$path} returned {$response->getStatusCode()}.");
        }
    }

    /** @return array{DOMDocument, DOMXPath} */
    private function dom(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        return [$dom, new DOMXPath($dom)];
    }
}
