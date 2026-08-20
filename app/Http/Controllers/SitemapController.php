<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Halaman publik yang boleh diindeks, dipetakan ke berkas sumber isinya.
     *
     * Wizard (/lapor, /lapor/{qr}, /lapor/langkah/{step}) dan /lapor-sukses
     * sengaja tidak dimasukkan karena halaman itu mengirim robots noindex.
     *
     * Nilai <lastmod> diambil dari mtime berkas sumber, bukan tanggal literal.
     * Alasannya: sitemap statis sebelumnya memakai tanggal tetap yang langsung
     * basi dan tidak akan pernah diperbarui siapa pun. Dengan mtime, tanggal
     * ikut bergerak sendiri begitu isi halaman benar-benar diubah. Pada
     * deployment yang menyalin ulang berkas (mis. build image Docker) mtime
     * jatuh ke waktu deploy — masih sinyal nyata, bukan angka mati.
     */
    private const PAGES = [
        'public.report' => ['resources/views/public/report-form.blade.php'],
        'seo.bullying-guide' => ['resources/views/public/seo/bullying-guide.blade.php'],
        'seo.student-violation' => ['resources/views/public/seo/topic.blade.php', 'app/Http/Controllers/SeoController.php'],
        'seo.facility-damage' => ['resources/views/public/seo/topic.blade.php', 'app/Http/Controllers/SeoController.php'],
        'seo.faq' => ['resources/views/public/seo/faq.blade.php'],
        'track.form' => ['resources/views/public/track.blade.php'],
    ];

    public function __invoke(): Response
    {
        $entries = [];
        foreach (self::PAGES as $routeName => $sources) {
            $entries[] = '  <url><loc>'.$this->escape(route($routeName)).'</loc>'
                .'<lastmod>'.$this->lastModified($sources).'</lastmod></url>';
        }

        // priority dan changefreq tidak ditulis: Google mengabaikan keduanya.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $entries)."\n"
            .'</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * mtime terbaru dari seluruh berkas sumber satu halaman, sebagai W3C
     * Datetime. Bila tidak ada berkas yang terbaca, pakai waktu permintaan
     * agar sitemap tetap valid daripada memancarkan tanggal kosong.
     */
    private function lastModified(array $sources): string
    {
        $timestamps = [];
        foreach ($sources as $source) {
            $modifiedAt = @filemtime(base_path($source));
            if ($modifiedAt !== false) {
                $timestamps[] = $modifiedAt;
            }
        }

        return date(DATE_ATOM, $timestamps === [] ? time() : max($timestamps));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
