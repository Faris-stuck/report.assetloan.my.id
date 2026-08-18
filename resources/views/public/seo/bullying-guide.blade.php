@extends('layouts.app')
@section('title','Lapor Pembullyan / Perundungan SMK Taruna Bangsa Bekasi')
@section('meta_title','Lapor Pembullyan SMK Taruna Bangsa Bekasi | LAPORIN')
@section('meta_description','Panduan lengkap cara lapor pembullyan, perundungan, pelanggaran siswa, dan kejadian tidak aman di SMK Taruna Bangsa Bekasi melalui LAPORIN.')
@section('canonical'){{ route('seo.bullying-guide') }}@endsection
@section('content')
@php
    $updatedAt = '2026-08-18';
    $faqs = [
        ['q' => 'Bagaimana cara lapor pembullyan di SMK Taruna Bangsa Bekasi?', 'a' => 'Buka LAPORIN, pilih jenis laporan Pelanggaran Siswa, isi identitas pelapor, detail kejadian, lokasi, kronologi, lalu kirim laporan. Sistem memberi nomor laporan dan kode akses untuk pelacakan status.'],
        ['q' => 'Apakah laporan perundungan bisa dilacak?', 'a' => 'Bisa. Setelah laporan terkirim, pelapor mendapat nomor laporan dan kode akses 6 digit untuk melihat perkembangan laporan di halaman Lacak Laporan.'],
        ['q' => 'Apa saja yang bisa dilaporkan lewat LAPORIN?', 'a' => 'LAPORIN dapat digunakan untuk laporan perundungan, pembullyan, bullying, pelanggaran tata tertib, kedisiplinan siswa, kejadian tidak aman, dan kerusakan fasilitas sekolah.'],
        ['q' => 'Apakah harus login untuk membuat laporan?', 'a' => 'Pelapor publik tidak perlu login. Pengelola sekolah seperti Kesiswaan, Sarpras, Wali Kelas, Guru, Siswa, dan SuperAdmin menggunakan login sesuai peran.'],
        ['q' => 'Apa yang harus dilakukan jika kejadiannya darurat?', 'a' => 'Jika ada ancaman langsung atau kondisi tidak aman, cari bantuan guru, wali kelas, kesiswaan, satpam, atau orang dewasa terdekat terlebih dahulu. LAPORIN bisa dipakai sebagai catatan laporan digital setelah kondisi aman.'],
    ];
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebPage',
                '@id' => route('seo.bullying-guide').'#webpage',
                'url' => route('seo.bullying-guide'),
                'name' => 'Lapor Pembullyan / Perundungan SMK Taruna Bangsa Bekasi',
                'description' => 'Panduan lengkap cara lapor pembullyan, perundungan, pelanggaran siswa, dan kejadian tidak aman di SMK Taruna Bangsa Bekasi melalui LAPORIN.',
                'inLanguage' => 'id-ID',
                'dateModified' => $updatedAt,
                'isPartOf' => ['@id' => url('/').'#website'],
                'breadcrumb' => ['@id' => route('seo.bullying-guide').'#breadcrumb'],
                'about' => [
                    ['@type' => 'Thing', 'name' => 'lapor pembullyan SMK Taruna Bangsa Bekasi'],
                    ['@type' => 'Thing', 'name' => 'lapor perundungan SMK Taruna Bangsa Bekasi'],
                    ['@type' => 'Thing', 'name' => 'sistem pelaporan bullying sekolah'],
                ],
                'mainEntity' => ['@id' => route('seo.bullying-guide').'#faq'],
            ],
            [
                '@type' => 'FAQPage',
                '@id' => route('seo.bullying-guide').'#faq',
                'mainEntity' => array_map(fn ($faq) => [
                    '@type' => 'Question',
                    'name' => $faq['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
                ], $faqs),
            ],
            [
                '@type' => 'BreadcrumbList',
                '@id' => route('seo.bullying-guide').'#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Panduan Lapor Pembullyan', 'item' => route('seo.bullying-guide')],
                ],
            ],
        ],
    ];
@endphp

<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('public.report') }}">Beranda LAPORIN</a></li>
        <li class="breadcrumb-item active" aria-current="page">Panduan Lapor Pembullyan</li>
    </ol>
</nav>

<div class="hero-card p-4 p-lg-5 mb-4">
    <div class="hero-content row align-items-center g-4">
        <div class="col-lg-8">
            <span class="page-kicker">Panduan laporan sekolah • diperbarui {{ $updatedAt }}</span>
            <h1 class="page-title display-6 mt-3">Lapor Pembullyan dan Perundungan di SMK Taruna Bangsa Bekasi</h1>
            <p class="page-subtitle fs-6">LAPORIN adalah kanal laporan online untuk membantu warga sekolah membuat laporan pembullyan, perundungan, pelanggaran siswa, atau kejadian tidak aman secara jelas, aman, dan bisa dilacak.</p>
            <div class="d-flex flex-wrap gap-2 mt-4 btn-group-mobile">
                <a class="btn btn-laporin" href="{{ route('public.report') }}#form-laporan">Buat Laporan Sekarang</a>
                <a class="btn btn-outline-laporin" href="{{ route('track.form') }}">Lacak Laporan</a>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="laporin-card bg-white h-100">
                <h2 class="h5 fw-bold">Jawaban cepat</h2>
                <p class="small-muted mb-0">Untuk melaporkan pembullyan atau perundungan di SMK Taruna Bangsa Bekasi, buka LAPORIN, pilih <strong>Pelanggaran Siswa</strong>, tulis kronologi, lokasi, waktu kejadian, pihak terdampak, dan bukti bila ada. Setelah laporan dikirim, simpan nomor laporan dan kode akses untuk memantau status melalui halaman Lacak Laporan.</p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <article class="laporin-card p-4 p-lg-5 seo-prose">
            <h2>Untuk apa halaman ini?</h2>
            <p>Halaman ini menjelaskan cara memakai LAPORIN untuk membuat laporan di lingkungan SMK Taruna Bangsa Bekasi. Topik yang bisa dilaporkan meliputi pembullyan, perundungan, bullying, pelanggaran tata tertib, kedisiplinan siswa, kejadian tidak aman, dan masalah lain yang perlu ditindaklanjuti pihak sekolah.</p>
            <p>LAPORIN dibuat agar laporan tidak hanya disampaikan secara lisan, tetapi juga tercatat dengan nomor laporan, status, alur penanganan, dan riwayat tindak lanjut. Dengan begitu pelapor dapat mengecek perkembangan tanpa harus menebak laporan sudah sampai ke siapa.</p>

            <h2>Kapan memakai LAPORIN?</h2>
            <ul>
                <li>Saat melihat atau mengalami pembullyan, perundungan, intimidasi, ejekan berulang, ancaman, pemaksaan, atau perlakuan yang membuat siswa tidak aman.</li>
                <li>Saat ada pelanggaran tata tertib atau kejadian kedisiplinan yang perlu diketahui Kesiswaan.</li>
                <li>Saat ada kerusakan fasilitas sekolah yang perlu ditangani Sarpras, seperti lampu, meja, kursi, toilet, pintu, AC, proyektor, atau instalasi listrik.</li>
                <li>Saat pelapor membutuhkan bukti nomor laporan dan status tindak lanjut.</li>
            </ul>
            <p>Jika situasinya darurat atau ada ancaman langsung, utamakan keselamatan terlebih dahulu. Hubungi guru, wali kelas, Kesiswaan, satpam, atau orang dewasa terdekat. LAPORIN dapat digunakan setelah kondisi lebih aman agar kejadian tetap tercatat.</p>

            <h2>Cara membuat laporan pembullyan/perundungan</h2>
            <ol>
                <li>Buka halaman <a href="{{ route('public.report') }}#form-laporan">Buat Laporan</a>.</li>
                <li>Pilih jenis laporan <strong>Pelanggaran Siswa</strong>.</li>
                <li>Isi identitas pelapor dan data kelas sesuai formulir.</li>
                <li>Tulis lokasi, tanggal, waktu, dan kronologi kejadian dengan bahasa yang jelas.</li>
                <li>Tambahkan bukti bila ada, seperti foto atau PDF. Jangan mengunggah data yang tidak relevan.</li>
                <li>Kirim laporan dan simpan <strong>nomor laporan</strong> serta <strong>kode akses</strong>.</li>
                <li>Cek perkembangan lewat halaman <a href="{{ route('track.form') }}">Lacak Laporan</a>.</li>
            </ol>

            <h2>Informasi yang sebaiknya ditulis</h2>
            <p>Agar petugas bisa menindaklanjuti tanpa menebak, tuliskan kronologi secara singkat dan jelas: apa yang terjadi, kapan terjadi, lokasi kejadian, siapa yang terdampak, saksi bila ada, dan kondisi terakhir. Hindari menulis tuduhan yang tidak bisa dijelaskan. Fokus pada kejadian yang benar-benar dilihat, dialami, atau diketahui pelapor.</p>
            <div class="alert alert-light border rounded-4">
                <strong>Contoh kronologi:</strong> “Telah terjadi perundungan di Lab Komputer pada jam istirahat. Korban diejek berulang dan barangnya disembunyikan. Ada beberapa siswa yang melihat kejadian. Saya membuat laporan agar wali kelas dan Kesiswaan dapat menindaklanjuti.”
            </div>

            <h2>Alur setelah laporan masuk</h2>
            <div class="flowchart compact my-3">
                <div class="flow-node">Laporan Masuk</div>
                <div class="flow-node">Kesiswaan</div>
                <div class="flow-node">Wali Kelas</div>
                <div class="flow-node">Konfirmasi Pelapor</div>
                <div class="flow-node">Selesai / Dibuka Kembali</div>
            </div>
            <p>Untuk laporan pelanggaran siswa, laporan diarahkan ke Kesiswaan. Jika perlu tindak lanjut kelas, laporan dapat diteruskan ke Wali Kelas. Setelah ditangani, pelapor dapat diminta mengonfirmasi apakah masalah sudah selesai atau masih perlu informasi tambahan.</p>
            <p>Jika pelapor memilih <strong>Konfirmasi Selesai</strong>, laporan masuk status selesai. Jika pelapor memilih <strong>Tambahkan Informasi</strong>, laporan dapat dibuka kembali agar petugas melihat keterangan baru.</p>

            <h2>Privasi dan keamanan laporan</h2>
            <p>Nomor laporan dan kode akses digunakan untuk melacak status. Jangan membagikan kode akses ke orang yang tidak berkepentingan. Halaman dashboard, detail laporan, lampiran, dan data internal tidak dimasukkan ke sitemap publik agar tidak menjadi target crawler search engine.</p>

            <h2>Kata kunci yang dijawab halaman ini</h2>
            <p>Halaman ini ditulis untuk membantu pencarian seperti “lapor pembullyan SMK Taruna Bangsa Bekasi”, “lapor perundungan SMK Taruna Bangsa Bekasi”, “sistem pelaporan bullying sekolah”, “lapor pelanggaran siswa”, dan “LAPORIN SMK Taruna Bangsa Bekasi”.</p>
        </article>
    </div>
    <div class="col-lg-4">
        <aside class="laporin-card p-4 sticky-lg-top seo-aside">
            <h2 class="h5 fw-bold">Akses cepat</h2>
            <div class="d-grid gap-2">
                <a class="btn btn-laporin" href="{{ route('public.report') }}#form-laporan">Buat Laporan</a>
                <a class="btn btn-outline-laporin" href="{{ route('track.form') }}">Lacak Laporan</a>
                <a class="btn btn-outline-laporin" href="{{ route('seo.faq') }}">Baca FAQ</a>
            </div>
            <hr>
            <h3 class="h6 fw-bold">Kata kunci relevan</h3>
            <ul class="small-muted mb-0 ps-3">
                <li>lapor pembullyan SMK Taruna Bangsa Bekasi</li>
                <li>lapor perundungan siswa</li>
                <li>lapor pelanggaran siswa</li>
                <li>lapor kerusakan fasilitas sekolah</li>
            </ul>
            <hr>
            <p class="small-muted mb-0">Gunakan bahasa yang jelas dan tidak melebih-lebihkan. Laporan palsu dapat menghambat penanganan kejadian nyata.</p>
        </aside>
    </div>
</div>

<div class="laporin-card p-4 p-lg-5">
    <h2 class="h4 fw-bold mb-3">FAQ Lapor Pembullyan / Perundungan</h2>
    <div class="accordion" id="seoFaq">
        @foreach($faqs as $i => $faq)
            <div class="accordion-item border-0 border-bottom">
                <h3 class="accordion-header" id="faqHeading{{ $i }}">
                    <button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faqCollapse{{ $i }}" aria-expanded="{{ $i ? 'false' : 'true' }}" aria-controls="faqCollapse{{ $i }}">
                        {{ $faq['q'] }}
                    </button>
                </h3>
                <div id="faqCollapse{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" aria-labelledby="faqHeading{{ $i }}" data-bs-parent="#seoFaq">
                    <div class="accordion-body small-muted">{{ $faq['a'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('head')
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
