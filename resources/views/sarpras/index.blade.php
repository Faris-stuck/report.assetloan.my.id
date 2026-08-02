@extends('layouts.app')
@section('title','Sarpras')
@section('content')
@php
    $processable = ['menunggu_verifikasi','memerlukan_informasi','dibuka_kembali','sedang_ditangani'];
    $minSchedule = now()->format('Y-m-d\TH:i');
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
<div class="page-header">
    <div>
        <span class="page-kicker">Sarpras</span>
        <h1 class="page-title h2 mt-2">Tindak Lanjut Kerusakan Fasilitas</h1>
        <p class="page-subtitle">Atur prioritas, waktu perbaikan, foto bukti, dan catatan pengerjaan dalam satu halaman.</p>
    </div>
</div>
<div class="laporin-card card-soft mb-4">
    <h2 class="h5 fw-bold mb-3">Alur Sarpras</h2>
    <div class="flowchart compact"><div class="flow-node">Laporan Masuk</div><div class="flow-node">Lokasi Dicek</div><div class="flow-node">Waktu Perbaikan</div><div class="flow-node">Perbaikan</div><div class="flow-node">Foto Selesai</div><div class="flow-node">Selesai</div></div>
</div>
<div class="report-card-list">
@forelse($reports as $r)
    <article class="report-row-card">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <div><a class="fw-bold" href="{{ route('reports.show',$r) }}">{{ $r->report_number }}</a><h2 class="h5 mb-1">{{ $r->title }}</h2><div class="report-meta"><span>{{ $r->created_at->format('d/m/Y H:i') }}</span><span>Kerusakan fasilitas</span></div></div>
            <span class="status-pill status-{{ $r->status }}">{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</span>
        </div>
        @if(in_array($r->status, $processable, true))
            <form method="POST" enctype="multipart/form-data" action="{{ route('sarpras.process',$r) }}" class="row g-3 align-items-end">@csrf
                <div class="col-lg-2"><label class="form-label required">Prioritas</label><select name="priority" class="form-select" required><option value="rendah">Rendah</option><option value="sedang" selected>Sedang</option><option value="tinggi">Tinggi</option><option value="darurat">Darurat</option></select></div>
                <div class="col-lg-3"><label class="form-label">Waktu Perbaikan</label><input type="datetime-local" name="scheduled_repair_at" min="{{ $minSchedule }}" class="form-control"></div>
                <div class="col-lg-3"><label class="form-label">Foto setelah diperbaiki</label><input type="file" name="repair_photo" class="form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></div>
                <div class="col-lg-3"><label class="form-label">Catatan</label><input name="note" class="form-control" placeholder="Opsional" maxlength="2000"></div>
                <div class="col-lg-1"><button class="btn btn-laporin w-100">Simpan</button></div>
            </form>
            <form method="POST" action="{{ route('sarpras.reject', $r) }}" class="row g-3 align-items-end mt-2" onsubmit="return confirm('Tolak laporan kerusakan ini? Alur laporan akan berhenti.')">
                @csrf
                <div class="col-lg-10"><label class="form-label required" for="reject_reason_{{ $r->id }}">Alasan penolakan</label><input id="reject_reason_{{ $r->id }}" name="reason" class="form-control" required maxlength="2000" placeholder="Jelaskan mengapa laporan tidak dapat diproses"></div>
                <div class="col-lg-2"><button class="btn btn-outline-danger w-100">Tolak Laporan</button></div>
            </form>
        @else
            <div class="status-note mb-0">
                Status laporan saat ini: <strong>{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</strong>.
                Tidak ada aksi Sarpras yang perlu dilakukan di tahap ini.
            </div>
        @endif
    </article>
@empty
    <div class="laporin-card text-center py-5 text-muted">Belum ada laporan kerusakan fasilitas.</div>
@endforelse
</div>
<div class="mt-3">{{ $reports->links() }}</div>
@endsection
