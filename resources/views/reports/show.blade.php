@extends('layouts.app')
@section('title','Detail Laporan')
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
    $roleLabels = [
        'superadmin' => 'Super Admin',
        'kesiswaan' => 'Kesiswaan',
        'sarpras' => 'Sarpras',
        'wali_kelas' => 'Wali Kelas',
        'guru' => 'Guru',
        'siswa' => 'Siswa',
        'reporter' => 'Pelapor',
    ];
    $visibilityLabels = [
        'internal' => 'Catatan internal petugas',
        'reporter_visible' => 'Bisa dilihat pelapor',
    ];
@endphp
<div class="laporin-card p-4 p-lg-5">
    <div class="page-header mb-4">
        <div>
            <span class="page-kicker">Detail laporan</span>
            <h1 class="page-title h3 mt-2">{{ $report->report_number }}</h1>
            <p class="page-subtitle">{{ $report->title }}</p>
        </div>
        <span class="status-pill status-{{ $report->status }}">{{ $statusLabels[$report->status] ?? str_replace('_',' ',$report->status) }}</span>
    </div>
    <div class="flowchart compact mb-4">@foreach($flow as $status=>$label)<div class="flow-node {{ $report->status === $status ? 'is-active' : '' }}">{{ $label }}</div>@endforeach</div>
    <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="detail-box h-100"><div class="small-muted">Pelapor</div><strong>{{ $report->reporter_name }}</strong><div>{{ ucfirst($report->reporter_type) }}</div></div></div>
        <div class="col-md-6"><div class="detail-box h-100"><div class="small-muted">Jenis Laporan</div><strong>{{ $report->report_type === 'violation' ? 'Pelanggaran siswa / perundungan' : 'Kerusakan fasilitas' }}</strong></div></div>
        <div class="col-md-6"><div class="detail-box h-100"><div class="small-muted">Waktu Kejadian</div><strong>{{ $report->incident_date->format('d/m/Y') }}</strong> {{ $report->incident_time }}</div></div>
        <div class="col-md-6"><div class="detail-box h-100"><div class="small-muted">Lokasi</div><strong>{{ $report->location?->location_name ?? $report->custom_location }}</strong></div></div>
        <div class="col-12"><div class="detail-box"><div class="small-muted">Kronologi / Deskripsi</div><p class="mb-0">{{ $report->description }}</p></div></div>
    </div>
    @if($report->attachments->count())
        <h2 class="h5 fw-bold mt-3">Lampiran Aman</h2>
        <div class="report-card-list mb-4">@foreach($report->attachments as $a)<div class="report-row-card d-flex justify-content-between flex-wrap gap-2"><a href="{{ route('attachments.download',$a) }}">{{ $a->original_name }}</a><span class="small-muted">{{ number_format($a->file_size/1024,1) }} KB</span></div>@endforeach</div>
    @endif
    <h2 class="h5 fw-bold mt-3">Catatan Laporan</h2>
    <ul class="list-group mb-3">@forelse($report->notes as $n)<li class="list-group-item"><strong>{{ $roleLabels[$n->author_type] ?? ucfirst(str_replace('_',' ', $n->author_type)) }}</strong> · {{ $visibilityLabels[$n->visibility] ?? $n->visibility }}<div>{{ $n->note }}</div></li>@empty<li class="list-group-item text-muted">Belum ada catatan.</li>@endforelse</ul>
    @can('comment',$report)<form method="POST" action="{{ route('reports.notes',$report) }}" class="detail-box">@csrf<label class="form-label required" for="note">Tambah Catatan</label><textarea id="note" name="note" class="form-control mb-2" required maxlength="3000" rows="4" placeholder="Tulis catatan tindak lanjut."></textarea><select name="visibility" class="form-select mb-3" required><option value="internal">Catatan internal petugas</option><option value="reporter_visible">Bisa dilihat pelapor</option></select><button class="btn btn-outline-laporin">Simpan Catatan</button></form>@endcan
</div>
@endsection
