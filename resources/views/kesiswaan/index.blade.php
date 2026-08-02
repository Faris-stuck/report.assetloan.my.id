@extends('layouts.app')
@section('title','Kesiswaan')
@section('content')
@php
    $processable = ['menunggu_verifikasi','memerlukan_informasi','dibuka_kembali'];
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
        <span class="page-kicker">Kesiswaan</span>
        <h1 class="page-title h2 mt-2">Validasi Pelanggaran Siswa</h1>
        <p class="page-subtitle">Cek laporan, pilih siswa yang terbukti, lalu simpan jenis pelanggaran agar poin siswa berkurang sesuai aturan.</p>
    </div>
</div>
<div class="laporin-card card-soft mb-4">
    <h2 class="h5 fw-bold mb-3">Alur Kesiswaan</h2>
    <div class="flowchart compact"><div class="flow-node">Laporan Masuk</div><div class="flow-node">Bukti Dicek</div><div class="flow-node">Pilih Siswa</div><div class="flow-node">Pilih Pelanggaran</div><div class="flow-node">Kesiswaan Menangani</div><div class="flow-node">Pelapor Konfirmasi</div></div>
</div>
<div class="report-card-list">
@forelse($reports as $r)
    <article class="report-row-card">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <div><a class="fw-bold" href="{{ route('reports.show',$r) }}">{{ $r->report_number }}</a><h2 class="h5 mb-1">{{ $r->title }}</h2><div class="report-meta"><span>{{ $r->created_at->format('d/m/Y H:i') }}</span><span>Pelanggaran siswa</span></div></div>
            <span class="status-pill status-{{ $r->status }}">{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</span>
        </div>
        @if(in_array($r->status, $processable, true))
            <form method="POST" action="{{ route('kesiswaan.process',$r) }}" class="row g-3 align-items-end mb-3">@csrf
                <div class="col-lg-4"><label class="form-label required">Siswa yang terbukti</label><select name="student_id" class="form-select" required><option value="">Pilih siswa</option>@foreach($students as $s)<option value="{{ $s->id }}">{{ $s->name }} - {{ $s->class?->class_name }}</option>@endforeach</select></div>
                <div class="col-lg-4"><label class="form-label required">Jenis pelanggaran</label><select name="violation_type_id" class="form-select" required><option value="">Pilih jenis</option>@foreach($types as $t)<option value="{{ $t->id }}">{{ $t->violation_name }} (-{{ $t->point_reduction }} poin)</option>@endforeach</select></div>
                <div class="col-lg-3"><label class="form-label">Catatan pembinaan</label><input name="note" class="form-control" placeholder="Opsional" maxlength="2000"></div>
                <div class="col-lg-1"><button class="btn btn-laporin w-100">Proses</button></div>
            </form>
            <form method="POST" action="{{ route('kesiswaan.reject',$r) }}" class="row g-3 align-items-end reject-report-form" onsubmit="return confirm('Tolak laporan ini? Alur laporan akan berhenti.')">@csrf
                <div class="col-lg-10"><label class="form-label required">Alasan penolakan</label><input name="reason" class="form-control" placeholder="Wajib diisi jika laporan ditolak" required maxlength="2000"></div>
                <div class="col-lg-2"><button class="btn btn-outline-danger w-100">Tolak</button></div>
            </form>
        @elseif($r->status === 'sedang_ditangani')
            <form method="POST" action="{{ route('kesiswaan.complete', $r) }}" class="row g-3 align-items-end" onsubmit="return confirm('Tandai penanganan Kesiswaan selesai dan minta konfirmasi pelapor?')">
                @csrf
                <div class="col-lg-10">
                    <label class="form-label" for="completion_note_{{ $r->id }}">Catatan penyelesaian Kesiswaan</label>
                    <input id="completion_note_{{ $r->id }}" name="note" class="form-control" maxlength="2000" placeholder="Ringkasan pembinaan atau tindak lanjut (opsional)">
                </div>
                <div class="col-lg-2"><button class="btn btn-laporin w-100">Selesaikan Penanganan</button></div>
            </form>
        @else
            <div class="status-note mb-0">
                Status laporan saat ini: <strong>{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</strong>.
                Tidak ada aksi Kesiswaan yang perlu dilakukan di tahap ini.
            </div>
        @endif
    </article>
@empty
    <div class="laporin-card text-center py-5 text-muted">Belum ada laporan pelanggaran.</div>
@endforelse
</div>
<div class="mt-3">{{ $reports->links() }}</div>
@endsection
