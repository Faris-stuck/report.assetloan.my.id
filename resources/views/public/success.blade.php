@extends('layouts.app')
@section('title','Laporan Terkirim - LAPORIN')
@section('meta_title','Laporan Terkirim - LAPORIN')
@section('meta_description','Konfirmasi pengiriman laporan LAPORIN. Simpan nomor laporan dan kode akses untuk melacak status secara aman.')
@section('robots','noindex, nofollow, noarchive')
@section('content')
<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="hero-card p-4 p-lg-5 text-center">
            <div class="hero-content">
                <span class="page-kicker">Berhasil Terkirim</span>
                <h1 class="page-title h2 mt-3">Laporan Berhasil Diterima</h1>
                <p class="page-subtitle mx-auto">Simpan nomor laporan dan kode akses di bawah ini. Anda dapat membuat laporan lain kapan saja dari halaman utama LAPORIN.</p>

                <div class="row g-3 justify-content-center mt-3">
                    <div class="col-md-6">
                        <div class="laporin-card access-code-box credential-card p-3 h-100">
                            <div class="small-muted mb-2">Nomor Laporan</div>
                            <div class="credential-copy-row">
                                <code id="report-number-value" class="credential-value">{{ $report->report_number }}</code>
                                <button type="button" class="btn btn-sm btn-outline-laporin credential-copy-button" data-copy-target="report-number-value" data-copy-name="Nomor laporan" aria-label="Salin nomor laporan">
                                    <span aria-hidden="true">⧉</span>
                                    <span data-copy-label>Salin</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="laporin-card access-code-box credential-card p-3 h-100">
                            <div class="small-muted mb-2">Kode Akses</div>
                            <div class="credential-copy-row">
                                <code id="access-code-value" class="credential-value">{{ $accessCode ?? '••••••' }}</code>
                                <button type="button" class="btn btn-sm btn-outline-laporin credential-copy-button" data-copy-target="access-code-value" data-copy-name="Kode akses" aria-label="Salin kode akses" @disabled(empty($accessCode))>
                                    <span aria-hidden="true">⧉</span>
                                    <span data-copy-label>Salin</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <p id="copy-status" class="visually-hidden" role="status" aria-live="polite"></p>

                @if(session('notification_message'))
                    <div class="alert alert-info mt-4 text-start" role="alert">
                        {{ session('notification_message') }}
                    </div>
                @endif

                <div class="alert alert-info mt-4 text-start" role="status">
                    <strong>Ingin membuat laporan lain?</strong><br>
                    <span class="small">Mulai laporan baru dari <a href="{{ url('/') }}" class="alert-link">halaman utama LAPORIN</a>. Batas pengiriman tetap mengikuti perlindungan anti-spam per perangkat.</span>
                </div>

                <div class="mt-3">
                    <a href="{{ route('track.form') }}" class="btn btn-laporin">Lacak Status Laporan</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const status = document.getElementById('copy-status');
    const fallbackCopy = (value) => {
        const textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        const copied = document.execCommand('copy');
        textarea.remove();
        if (!copied) throw new Error('Copy command failed');
    };

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = document.getElementById(button.dataset.copyTarget);
            const value = target?.textContent?.trim();
            if (!value) return;
            try {
                if (navigator.clipboard?.writeText && window.isSecureContext) {
                    await navigator.clipboard.writeText(value);
                } else {
                    fallbackCopy(value);
                }
                const label = button.querySelector('[data-copy-label]');
                const original = label?.textContent || 'Salin';
                if (label) label.textContent = 'Tersalin';
                button.classList.add('is-copied');
                if (status) status.textContent = `${button.dataset.copyName} berhasil disalin.`;
                window.setTimeout(() => {
                    if (label) label.textContent = original;
                    button.classList.remove('is-copied');
                }, 1800);
            } catch (_) {
                if (status) status.textContent = `${button.dataset.copyName} gagal disalin. Pilih kode lalu salin secara manual.`;
            }
        });
    });
});
</script>
@endpush
