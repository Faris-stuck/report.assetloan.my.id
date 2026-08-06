@extends('layouts.app')
@section('title','Lacak Laporan LAPORIN')
@section('meta_title','Lacak Laporan LAPORIN SMK Taruna Bangsa Bekasi')
@section('meta_description','Lacak status laporan perundungan, pembullyan, pelanggaran siswa, atau kerusakan fasilitas menggunakan nomor laporan dan kode akses. Pelacakan laporan dibatasi untuk menjaga privasi.')
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
            <form method="POST" action="{{ route('track.search') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label required" for="report_number">Nomor Laporan</label>
                    <input id="report_number" name="report_number" value="{{ old('report_number') }}" class="form-control" placeholder="LPR2026070001" required autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="next" aria-describedby="report-number-help" data-normalize-report-number>
                    <div id="report-number-help" class="helper-text">Contoh yang dapat langsung ditempel: <strong>LPR2026070001</strong>. Spasi atau tanda hubung dari hasil salin-tempel akan dihapus otomatis.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label required" for="access_code">Kode Akses</label>
                    <input id="access_code" name="access_code" value="{{ old('access_code') }}" class="form-control" inputmode="numeric" required autocomplete="one-time-code" enterkeyhint="search" placeholder="Contoh: 123456" aria-describedby="access-code-help" data-normalize-access-code>
                    <div id="access-code-help" class="helper-text">Masukkan 6 angka. Spasi atau tanda hubung hasil salin-tempel akan dihapus otomatis.</div>
                </div>
                <button class="btn btn-laporin w-100">Lacak Laporan</button>
            </form>
        </div>
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
