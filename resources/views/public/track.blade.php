@extends('layouts.app')
@section('title','Lacak Laporan LAPORIN')
@section('meta_title','Lacak Laporan SMK Taruna Bangsa Bekasi | Cek Status LAPORIN')
@section('meta_description','Cek status laporan LAPORIN SMK Taruna Bangsa Bekasi dengan nomor laporan dan kode akses. Lacak laporan perundungan, pelanggaran siswa, dan kerusakan fasilitas secara aman.')
@section('canonical'){{ route('track.form') }}@endsection
@section('content')
<div class="tracking-shell">
    <section class="tracking-overview" aria-labelledby="tracking-title">
        <span class="page-kicker">Pelacakan aman</span>
        <h1 id="tracking-title" class="page-title h2 mt-2">Lacak status laporan</h1>
        <p class="page-subtitle">Masukkan nomor laporan dan kode akses 6 digit yang muncul setelah laporan terkirim. Data pelacakan dibatasi agar privasi tetap terjaga.</p>
        <div class="laporin-card card-soft mt-4">
            <div class="flowchart compact">
                <div class="flow-node">Masuk</div>
                <div class="flow-node">Diproses</div>
                <div class="flow-node">Konfirmasi</div>
                <div class="flow-node">Selesai</div>
            </div>
        </div>
    </section>
    <section class="tracking-form-panel" aria-labelledby="tracking-form-title">
        <div class="laporin-card p-4 p-lg-5">
            <h2 id="tracking-form-title" class="h4 fw-bold mb-1">Formulir Pelacakan</h2>
            <p class="small-muted mb-4">Salin dan tempel nomor laporan serta kode akses dari halaman laporan terkirim.</p>
            <form method="POST" action="{{ route('track.search') }}" class="row g-3">
                @csrf
                <div class="col-12">
                    <label class="form-label required" for="report_number">Nomor Laporan</label>
                    <input id="report_number" name="report_number" value="{{ old('report_number') }}" class="form-control" placeholder="LAP-ABC234-XYZ789" required autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="next" maxlength="24" aria-describedby="report-number-help" data-normalize-report-number>
                    <small id="report-number-help" class="text-muted">Contoh yang dapat langsung ditempel: <strong>LAP-ABC234-XYZ789</strong>. Spasi atau tanda hubung dari hasil salin-tempel akan dihapus otomatis.</small>
                </div>
                <div class="col-12">
                    <label class="form-label required" for="access_code">Kode Akses</label>
                    <input id="access_code" name="access_code" value="{{ old('access_code') }}" class="form-control" inputmode="numeric" required autocomplete="one-time-code" enterkeyhint="search" placeholder="Contoh: 123456" maxlength="16" aria-describedby="access-code-help" data-normalize-access-code>
                    <small id="access-code-help" class="text-muted">Masukkan 6 angka. Spasi atau tanda hubung hasil salin-tempel akan dihapus otomatis.</small>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-laporin w-100">Lacak Laporan</button>
                </div>
            </form>
        </div>
    </section>
    <section class="laporin-card p-4 p-lg-5 mt-4 seo-prose" aria-labelledby="tracking-help-title">
        <h2 id="tracking-help-title">Cara melacak laporan LAPORIN</h2>
        <p>Masukkan nomor laporan dan kode akses yang Anda terima setelah laporan dikirim. Nomor laporan membantu menemukan laporan, sedangkan kode akses digunakan untuk menjaga agar status hanya dapat dibuka oleh pihak yang memiliki akses.</p>
        <p>Untuk panduan membuat laporan, baca <a href="{{ route('seo.bullying-guide') }}">panduan lapor pembullyan/perundungan</a> atau <a href="{{ route('seo.faq') }}">FAQ LAPORIN</a>.</p>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const reportNumber = document.querySelector('[data-normalize-report-number]');
    const accessCode = document.querySelector('[data-normalize-access-code]');

    const bindNormalizer = (input, normalize) => {
        if (!input) return;
        const apply = () => { input.value = normalize(input.value); };
        input.addEventListener('input', apply);
        input.addEventListener('blur', apply);
    };

    bindNormalizer(reportNumber, (value) => value.toUpperCase().replace(/[^A-Z0-9-]/g, ''));
    bindNormalizer(accessCode, (value) => value.replace(/[^0-9]/g, ''));
});
</script>
@endpush
