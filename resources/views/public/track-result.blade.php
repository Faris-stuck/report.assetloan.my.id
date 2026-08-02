@extends('layouts.app')
@section('title','Status Laporan')
@section('content')
@php
    $flow = [
        'menunggu_verifikasi' => 'Laporan Masuk',
        'memerlukan_informasi' => 'Butuh Info Tambahan',
        'dibuka_kembali' => 'Info Diterima',
        'sedang_ditangani' => 'Sedang Ditangani',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'selesai' => 'Selesai',
    ];
    $statusLabels = [
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'memerlukan_informasi' => 'Perlu Informasi Tambahan',
        'dibuka_kembali' => 'Dibuka Kembali',
        'sedang_ditangani' => 'Sedang Ditangani',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi Pelapor',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ];
@endphp
<div class="laporin-card p-4 p-lg-5">
    <div class="page-header mb-4">
        <div>
            <span class="page-kicker">Status laporan</span>
            <h1 class="page-title h3 mt-2">{{ $report->report_number }}</h1>
            <p class="page-subtitle">{{ $report->title }}</p>
        </div>
        <span class="status-pill status-{{ $report->status }}">{{ $statusLabels[$report->status] ?? str_replace('_',' ', $report->status) }}</span>
    </div>
    <div class="flowchart compact mb-4">
        @foreach($flow as $status=>$label)
            <div class="flow-node {{ $report->status === $status ? 'is-active' : '' }}">{{ $label }}</div>
        @endforeach
    </div>
    @if($report->status === 'ditolak')<div class="alert alert-danger">Laporan ditolak. Alur pemrosesan berhenti.</div>@endif
    @if($report->status === 'menunggu_konfirmasi')
        <div class="alert alert-info">Petugas menyatakan laporan sudah ditangani. Jika kondisi di lapangan sudah benar, klik <strong>Konfirmasi Selesai</strong>. Jika belum sesuai, tambahkan informasi agar petugas menindaklanjuti kembali.</div>
    @endif
    <div class="detail-box mb-4"><h2 class="h6 fw-bold">Ringkasan untuk pelapor</h2><p class="mb-0">{{ $report->description }}</p></div>
    <h2 class="h5 fw-bold">Riwayat Laporan</h2>
    <ul class="list-group mb-4">
        @forelse($report->histories as $h)
            <li class="list-group-item border-0 border-bottom px-0"><strong>{{ $statusLabels[$h->new_status] ?? str_replace('_',' ', $h->new_status) }}</strong><div class="small-muted">{{ $h->created_at->format('d/m/Y H:i') }}</div>@if($h->public_note)<div class="mt-1">{{ $h->public_note }}</div>@endif</li>
        @empty
            <li class="list-group-item border-0 px-0">Belum ada riwayat.</li>
        @endforelse
    </ul>
    @if(in_array($report->status, ['memerlukan_informasi','dibuka_kembali','menunggu_konfirmasi']))
        <form method="POST" action="{{ route('track.info', $report) }}" class="detail-box mb-3">@csrf<label class="form-label required" for="note">Tambahkan Informasi</label><textarea id="note" name="note" class="form-control mb-3" required maxlength="3000" rows="4" placeholder="Tulis informasi tambahan atau alasan jika laporan belum selesai."></textarea><button class="btn btn-outline-laporin">Kirim Informasi</button></form>
    @endif
    @if($report->status === 'menunggu_konfirmasi')
        <form method="POST" action="{{ route('track.confirm', $report) }}" onsubmit="return confirm('Konfirmasi bahwa laporan ini sudah selesai?')">@csrf<button class="btn btn-laporin">Konfirmasi Selesai</button></form>
    @endif
</div>
@endsection
