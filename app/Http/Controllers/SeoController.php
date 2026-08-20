<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SeoController extends Controller
{
    public function bullyingGuide(): View
    {
        return view('public.seo.bullying-guide');
    }

    public function faq(): View
    {
        return view('public.seo.faq');
    }

    public function studentViolation(): View
    {
        return view('public.seo.topic', ['page' => $this->topicPage(
            'Lapor Pelanggaran Siswa | LAPORIN SMK Taruna Bangsa Bekasi',
            'Panduan menggunakan LAPORIN untuk melaporkan pelanggaran siswa, kedisiplinan, tata tertib, perundungan, dan kejadian tidak aman di SMK Taruna Bangsa Bekasi.',
            'Lapor Pelanggaran Siswa di SMK Taruna Bangsa Bekasi',
            'Panduan singkat untuk membuat laporan pelanggaran siswa dan kejadian tidak aman dengan kronologi yang jelas serta dapat dilacak.',
            [
                ['h2' => 'Apa yang dapat dilaporkan?', 'paragraphs' => ['Gunakan LAPORIN untuk mencatat <strong>pelanggaran tata tertib, kedisiplinan, perundungan, pembullyan, intimidasi, ancaman, atau kejadian lain yang perlu diketahui Kesiswaan</strong>. Fokuskan laporan pada kejadian yang benar-benar dilihat, dialami, atau diketahui pelapor.'], 'items' => ['Perundungan atau bullying yang berulang.', 'Pelanggaran tata tertib dan kedisiplinan siswa.', 'Kejadian tidak aman yang membutuhkan tindak lanjut sekolah.', 'Informasi tambahan untuk laporan yang sudah dibuat.']],
                ['h2' => 'Informasi yang sebaiknya ditulis', 'paragraphs' => ['Kronologi yang baik menjawab apa yang terjadi, kapan, di mana, siapa yang terdampak, siapa yang terlibat bila diketahui, dan apakah ada saksi atau bukti. Tuliskan urutan kejadian dengan bahasa netral dan jelas.'], 'items' => ['Tanggal dan lokasi kejadian.', 'Kronologi singkat dan urut.', 'Nama pihak yang relevan sesuai informasi yang memang diketahui.', 'Bukti yang relevan bila tersedia.']],
                ['h2' => 'Bagaimana alur tindak lanjut?', 'paragraphs' => ['Laporan pelanggaran siswa diarahkan ke Kesiswaan untuk ditinjau dan diproses. Bila memerlukan koordinasi kelas, tindak lanjut dapat melibatkan Wali Kelas. Pelapor menggunakan nomor laporan dan kode akses untuk memantau status.'], 'items' => []],
                ['h2' => 'Cara membuat laporan', 'paragraphs' => ['Buka <a href="'.route('public.report').'">LAPORIN</a>, pilih jenis laporan <strong>Pelanggaran Siswa</strong>, isi identitas dan detail kejadian, unggah bukti bila perlu, lalu kirim. Setelah berhasil, simpan nomor laporan dan kode akses untuk <a href="'.route('track.form').'">melacak status</a>.'], 'items' => []],
            ]
        )]);
    }

    public function facilityDamage(): View
    {
        return view('public.seo.topic', ['page' => $this->topicPage(
            'Lapor Kerusakan Fasilitas | LAPORIN SMK Taruna Bangsa Bekasi',
            'Panduan melaporkan kerusakan fasilitas sekolah seperti kelas, laboratorium, toilet, listrik, AC, proyektor, meja, kursi, pintu, dan sarana lain melalui LAPORIN.',
            'Lapor Kerusakan Fasilitas Sekolah di SMK Taruna Bangsa Bekasi',
            'Gunakan LAPORIN untuk mencatat kerusakan fasilitas secara jelas agar petugas Sarpras dapat menilai dan menindaklanjuti laporan.',
            [
                ['h2' => 'Fasilitas apa saja yang dapat dilaporkan?', 'paragraphs' => ['LAPORIN dapat dipakai untuk melaporkan <strong>kerusakan fasilitas sekolah</strong> seperti meja, kursi, pintu, proyektor, AC, toilet, lampu, instalasi listrik, dan sarana sekolah lain yang memerlukan perbaikan.'], 'items' => ['Ruang kelas dan laboratorium.', 'Peralatan pembelajaran dan perangkat elektronik.', 'Toilet, pintu, jendela, dan perlengkapan bangunan.', 'Lampu, listrik, AC, jaringan, atau fasilitas penunjang lain.']],
                ['h2' => 'Data yang membuat laporan mudah ditindaklanjuti', 'paragraphs' => ['Tuliskan nama barang atau fasilitas, lokasi, tanggal kejadian bila diketahui, kondisi kerusakan, dampak terhadap kegiatan, dan penyebab yang dicurigai hanya jika memang diketahui. Foto dapat membantu petugas memahami kondisi.'], 'items' => ['Nama fasilitas/barang.', 'Lokasi yang spesifik, misalnya ruang atau area sekolah.', 'Deskripsi kerusakan dan dampaknya.', 'Foto atau dokumen pendukung bila relevan.']],
                ['h2' => 'Alur setelah laporan dikirim', 'paragraphs' => ['Laporan kerusakan fasilitas diarahkan ke Sarpras untuk diproses. Pelapor dapat menggunakan nomor laporan dan kode akses untuk melihat status, serta menambahkan informasi jika diperlukan.'], 'items' => []],
                ['h2' => 'Cara membuat laporan kerusakan', 'paragraphs' => ['Buka <a href="'.route('public.report').'">LAPORIN</a>, pilih <strong>Kerusakan Fasilitas</strong>, isi nama barang/fasilitas dan deskripsi kerusakan, lampirkan bukti bila perlu, lalu kirim. Simpan nomor laporan dan kode akses untuk <a href="'.route('track.form').'">melacak status</a>.'], 'items' => []],
            ]
        )]);
    }

    /**
     * Satu judul dipakai untuk <title> maupun meta_title. Sebelumnya string
     * yang sama dikirim dua kali sebagai argumen terpisah, jadi keduanya bisa
     * berbeda tanpa disadari padahal layout hanya memakai meta_title.
     */
    private function topicPage(string $title, string $description, string $heading, string $intro, array $sections): array
    {
        $url = url()->current();
        $updated = '2026-08-18';
        return [
            'title' => $title,
            'meta_title' => $title,
            'description' => $description,
            'heading' => $heading,
            'intro' => $intro,
            'updated' => $updated,
            'url' => $url,
            'sections' => $sections,
            'related' => $this->relatedLinks($url),
            'jsonld' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'WebPage',
                        '@id' => $url.'#webpage',
                        'url' => $url,
                        'name' => $heading,
                        'description' => $description,
                        'dateModified' => $updated,
                        'inLanguage' => 'id-ID',
                        'isPartOf' => ['@id' => url('/').'#website'],
                        'breadcrumb' => ['@id' => $url.'#breadcrumb'],
                    ],
                    [
                        '@type' => 'BreadcrumbList',
                        '@id' => $url.'#breadcrumb',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => url('/')],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => $heading, 'item' => $url],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Tautan silang ke halaman panduan lain dan ke pelacakan laporan. Halaman
     * yang sedang dibuka disaring lewat perbandingan URL supaya kedua halaman
     * topik tidak pernah menaut ke dirinya sendiri.
     */
    private function relatedLinks(string $currentUrl): array
    {
        $targets = [
            'seo.bullying-guide' => 'Panduan lapor pembullyan dan perundungan',
            'seo.student-violation' => 'Panduan lapor pelanggaran siswa',
            'seo.facility-damage' => 'Panduan lapor kerusakan fasilitas sekolah',
            'seo.faq' => 'Pertanyaan umum LAPORIN',
            'track.form' => 'Lacak status laporan',
        ];

        $links = [];
        foreach ($targets as $routeName => $label) {
            $target = route($routeName);
            if ($target === $currentUrl) {
                continue;
            }
            $links[] = ['url' => $target, 'label' => $label];
        }

        return $links;
    }
}
