<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTechnicalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_homepage_has_search_and_ai_readable_seo_signals(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('LAPORIN SMK Taruna Bangsa Bekasi | Lapor Perundungan', false)
            ->assertSee('name="description"', false)
            ->assertSee('LAPORIN SMK Taruna Bangsa Bekasi untuk melaporkan perundungan, pelanggaran siswa, dan kerusakan fasilitas', false)
            ->assertSee('rel="canonical"', false)
            ->assertSee('rel="sitemap"', false)
            ->assertSee('rel="alternate" type="text/plain" title="Konteks LLM"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('content="summary_large_image"', false)
            ->assertSee('name="twitter:image"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('Kanal laporan resmi SMK Taruna Bangsa Bekasi')
            ->assertSee(route('seo.bullying-guide'), false)
            ->assertSee(route('seo.faq'), false);
    }

    public function test_seo_landing_pages_have_canonical_schema_and_target_content(): void
    {
        $this->get(route('seo.bullying-guide'))
            ->assertOk()
            ->assertSee('<title>Lapor Pembullyan SMK Taruna Bangsa Bekasi | LAPORIN</title>', false)
            ->assertSee('rel="canonical" href="'.route('seo.bullying-guide').'"', false)
            ->assertSee('FAQPage', false)
            ->assertSee('BreadcrumbList', false)
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('Lapor Pembullyan dan Perundungan di SMK Taruna Bangsa Bekasi')
            ->assertSee('Buat Laporan Sekarang');

        $this->get(route('seo.faq'))
            ->assertOk()
            ->assertSee('<title>Pertanyaan Umum LAPORIN SMK Taruna Bangsa Bekasi | Lapor Perundungan</title>', false)
            ->assertSee('rel="canonical" href="'.route('seo.faq').'"', false)
            ->assertSee('FAQPage', false)
            ->assertSee('WebPage', false)
            ->assertSee('BreadcrumbList', false)
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('Bagaimana cara lapor pembullyan atau perundungan?')
            ->assertSee('Lacak Laporan');
    }

    public function test_login_surface_is_explicitly_excluded_from_search_results(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('name="robots" content="noindex, nofollow, noarchive"', false);
    }

    public function test_crawler_files_expose_public_pages_and_block_private_surfaces(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('Sitemap: https://report.assetloan.my.id/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
        $this->assertStringContainsString('Disallow: /reports', $robots);
        $this->assertStringContainsString('Disallow: /download-attachment', $robots);

        // Sitemap kini dihasilkan SitemapController, bukan berkas statis di
        // public/, jadi harus diambil lewat HTTP. Loc dibandingkan dengan
        // route() agar tes tidak terikat pada APP_URL tertentu.
        $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('<loc>'.route('public.report').'</loc>', $sitemap);
        $this->assertStringContainsString('<loc>'.route('seo.bullying-guide').'</loc>', $sitemap);
        $this->assertStringContainsString('<loc>'.route('seo.faq').'</loc>', $sitemap);
        $this->assertStringNotContainsString('/admin', $sitemap);
        $this->assertStringNotContainsString('/reports', $sitemap);

        $llms = file_get_contents(public_path('llms.txt'));
        $this->assertStringContainsString('LAPORIN SMK Taruna Bangsa Bekasi', $llms);
        $this->assertStringContainsString('lapor pembullyan SMK Taruna Bangsa Bekasi', $llms);
        $this->assertStringContainsString('https://report.assetloan.my.id/lapor-pembullyan-smk-taruna-bangsa-bekasi', $llms);
    }
}
