@extends('layouts.app')
@section('title','Pertanyaan Umum LAPORIN SMK Taruna Bangsa Bekasi')
@section('meta_title','Pertanyaan Umum LAPORIN SMK Taruna Bangsa Bekasi | Lapor Perundungan')
@section('meta_description','Pertanyaan umum tentang LAPORIN untuk lapor perundungan, pembullyan, pelanggaran siswa, kerusakan fasilitas, dan pelacakan laporan.')
@section('canonical'){{ route('seo.faq') }}@endsection
@section('content')
@php
    $updatedAt = '2026-07-30';
    $faqs = [
        ['q' => 'Apa itu LAPORIN SMK Taruna Bangsa Bekasi?', 'a' => 'LAPORIN adalah sistem pelaporan berbasis web untuk membantu warga SMK Taruna Bangsa Bekasi membuat laporan perundungan, pembullyan, pelanggaran siswa, dan kerusakan fasilitas secara lebih rapi, aman, dan terlacak.'],
        ['q' => 'Bagaimana cara lapor pembullyan atau perundungan?', 'a' => 'Buka halaman Buat Laporan, pilih Pelanggaran Siswa, isi data pelapor, kronologi, lokasi, waktu kejadian, dan bukti bila ada, lalu kirim laporan. Setelah terkirim, simpan nomor laporan dan kode akses untuk pelacakan.'],
        ['q' => 'Apakah laporan kerusakan fasilitas juga bisa dibuat?', 'a' => 'Bisa. Pilih jenis laporan Kerusakan Fasilitas untuk melaporkan kerusakan seperti lampu, meja, kursi, proyektor, AC, toilet, pintu, jaringan, atau instalasi listrik.'],
        ['q' => 'Bagaimana cara mengecek status laporan?', 'a' => 'Gunakan menu Lacak Laporan. Masukkan nomor laporan dengan format LPRYYYYMM#### dan kode akses 6 digit yang didapat setelah laporan terkirim untuk pelacakan.'],
        ['q' => 'Siapa yang menangani laporan pelanggaran siswa?', 'a' => 'Laporan pelanggaran siswa diteruskan ke Kesiswaan. Jika perlu tindak lanjut kelas, laporan dapat diteruskan ke Wali Kelas untuk proses konfirmasi pelapor.'],
        ['q' => 'Apa yang terjadi setelah status Menunggu Konfirmasi Pelapor?', 'a' => 'Pelapor dapat mengonfirmasi selesai jika masalah sudah selesai, atau menambahkan informasi jika laporan perlu dibuka kembali.'],
        ['q' => 'Apakah pelapor publik harus login?', 'a' => 'Tidak. Pelapor publik dapat membuat laporan tanpa login. Login hanya digunakan oleh pengelola internal seperti SuperAdmin, Kesiswaan, Sarpras, Wali Kelas, Guru, dan Siswa.'],
        ['q' => 'Apa bedanya nomor laporan dan kode akses?', 'a' => 'Nomor laporan adalah identitas laporan. Kode akses adalah kode 6 digit untuk membuka halaman pelacakan laporan. Keduanya harus disimpan dan tidak dibagikan sembarangan.'],
        ['q' => 'Apa yang harus dilakukan jika kejadian bersifat darurat?', 'a' => 'Jika ada ancaman langsung, korban tidak aman, atau membutuhkan bantuan cepat, cari bantuan guru, wali kelas, Kesiswaan, satpam, atau orang dewasa terdekat terlebih dahulu. LAPORIN bisa digunakan setelah kondisi lebih aman.'],
    ];
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebPage',
                '@id' => route('seo.faq').'#webpage',
                'url' => route('seo.faq'),
                'name' => 'FAQ LAPORIN SMK Taruna Bangsa Bekasi',
                'description' => 'Pertanyaan umum tentang LAPORIN untuk lapor perundungan, pembullyan, pelanggaran siswa, kerusakan fasilitas, dan tracking laporan.',
                'dateModified' => $updatedAt,
                'inLanguage' => 'id-ID',
                'isPartOf' => ['@id' => url('/').'#website'],
                'mainEntity' => ['@id' => route('seo.faq').'#faq'],
                'breadcrumb' => ['@id' => route('seo.faq').'#breadcrumb'],
            ],
            [
                '@type' => 'FAQPage',
                '@id' => route('seo.faq').'#faq',
                'dateModified' => $updatedAt,
                'inLanguage' => 'id-ID',
                'mainEntity' => array_map(fn ($faq) => [
                    '@type' => 'Question',
                    'name' => $faq['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
                ], $faqs),
            ],
            [
                '@type' => 'BreadcrumbList',
                '@id' => route('seo.faq').'#breadcrumb',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'FAQ LAPORIN', 'item' => route('seo.faq')],
                ],
            ],
        ],
    ];
@endphp

<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('public.report') }}">Beranda LAPORIN</a></li>
        <li class="breadcrumb-item active" aria-current="page">Pertanyaan Umum LAPORIN</li>
    </ol>
</nav>

<div class="hero-card p-4 p-lg-5 mb-4">
    <div class="hero-content">
        <span class="page-kicker">Pertanyaan Umum LAPORIN • diperbarui {{ $updatedAt }}</span>
        <h1 class="page-title display-6 mt-3">Pertanyaan Umum Lapor Perundungan, Pembullyan, Pelanggaran, dan Kerusakan Fasilitas</h1>
        <p class="page-subtitle fs-6">Jawaban singkat untuk warga SMK Taruna Bangsa Bekasi yang ingin memakai LAPORIN dengan benar.</p>
        <div class="d-flex flex-wrap gap-2 mt-4 btn-group-mobile">
            <a class="btn btn-laporin" href="{{ route('public.report') }}#form-laporan">Buat Laporan</a>
            <a class="btn btn-outline-laporin" href="{{ route('seo.bullying-guide') }}">Panduan Lapor Pembullyan</a>
        </div>
    </div>
</div>

<div class="laporin-card p-4 p-lg-5 seo-prose mb-4">
    <h2>Ringkasan cepat</h2>
    <p>LAPORIN SMK Taruna Bangsa Bekasi adalah kanal laporan online untuk pembullyan, perundungan, pelanggaran siswa, dan kerusakan fasilitas. Pelapor publik dapat membuat laporan tanpa login, lalu mendapatkan nomor laporan dan kode akses. Nomor laporan dipakai untuk identifikasi, sedangkan kode akses dipakai untuk membuka halaman tracking. Jika laporan berkaitan dengan keselamatan langsung, pelapor tetap perlu mencari bantuan guru atau petugas sekolah terlebih dahulu.</p>
</div>

<div class="laporin-card p-4 p-lg-5">
    <div class="accordion" id="faqPageAccordion">
        @foreach($faqs as $i => $faq)
            <div class="accordion-item border-0 border-bottom">
                <h2 class="accordion-header" id="faqPageHeading{{ $i }}">
                    <button class="accordion-button {{ $i ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faqPageCollapse{{ $i }}" aria-expanded="{{ $i ? 'false' : 'true' }}" aria-controls="faqPageCollapse{{ $i }}">
                        {{ $faq['q'] }}
                    </button>
                </h2>
                <div id="faqPageCollapse{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" aria-labelledby="faqPageHeading{{ $i }}" data-bs-parent="#faqPageAccordion">
                    <div class="accordion-body">{{ $faq['a'] }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-md-4">
        <div class="laporin-card h-100">
            <h2 class="h5 fw-bold">Untuk laporan pelanggaran siswa</h2>
            <p class="small-muted">Gunakan jika ada perundungan, pembullyan, kedisiplinan, atau pelanggaran tata tertib. Laporan diarahkan ke Kesiswaan.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="laporin-card h-100">
            <h2 class="h5 fw-bold">Untuk laporan fasilitas sekolah</h2>
            <p class="small-muted">Gunakan jika ada fasilitas rusak seperti lampu, meja, kursi, toilet, AC, proyektor, pintu, atau listrik. Laporan diarahkan ke Sarpras.</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="laporin-card h-100">
            <h2 class="h5 fw-bold">Untuk tracking laporan</h2>
            <p class="small-muted">Gunakan nomor laporan dan kode akses untuk melihat status. Jangan membagikan kode akses kepada orang yang tidak berkepentingan.</p>
        </div>
    </div>
</div>
@endsection

@push('head')
<script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
