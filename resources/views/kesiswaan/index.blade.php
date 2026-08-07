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

<!-- Search & Filter Card -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('kesiswaan.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Cari nomor atau judul laporan..." value="{{ request('search') }}" maxlength="100">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                <option value="menunggu_verifikasi" @selected(request('status') === 'menunggu_verifikasi')>Menunggu Verifikasi</option>
                <option value="memerlukan_informasi" @selected(request('status') === 'memerlukan_informasi')>Perlu Informasi</option>
                <option value="dibuka_kembali" @selected(request('status') === 'dibuka_kembali')>Dibuka Kembali</option>
                <option value="sedang_ditangani" @selected(request('status') === 'sedang_ditangani')>Sedang Ditangani</option>
                <option value="menunggu_konfirmasi" @selected(request('status') === 'menunggu_konfirmasi')>Menunggu Konfirmasi</option>
                <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
                <option value="ditolak" @selected(request('status') === 'ditolak')>Ditolak</option>
            </select>
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="from_date">Dari</label>
            <input id="from_date" name="from_date" type="date" class="form-control" value="{{ request('from_date') }}">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="to_date">Sampai</label>
            <input id="to_date" name="to_date" type="date" class="form-control" value="{{ request('to_date') }}">
        </div>

        <div class="col-md-6 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
            <a href="{{ route('kesiswaan.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Results Info -->
@if(request('search') || request('status') || request('from_date') || request('to_date'))
    <div class="laporin-card mb-3 pb-3 border-bottom">
        <p class="text-muted small mb-0">
            Menampilkan {{ $reports->count() }} dari {{ $reports->total() }} laporan
            @if(request('search'))
                untuk pencarian "<strong>{{ request('search') }}</strong>"
            @endif
        </p>
    </div>
@endif

<div class="report-card-list">
@forelse($reports as $r)
    <article class="report-row-card">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <div><a class="fw-bold" href="{{ route('reports.show',$r) }}">{{ $r->report_number }}</a><h2 class="h5 mb-1">{{ $r->title }}</h2><div class="report-meta"><span>{{ $r->created_at->format('d/m/Y H:i') }}</span><span>Pelanggaran siswa</span></div></div>
            <span class="status-pill status-{{ $r->status }}">{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</span>
        </div>
        @if(in_array($r->status, $processable, true))
            <div class="accordion" id="accordion-kesiswaan-{{ $r->id }}">
                <!-- Process Tab -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#process-{{ $r->id }}" aria-expanded="true" aria-controls="process-{{ $r->id }}">
                            Proses Laporan
                        </button>
                    </h2>
                    <div id="process-{{ $r->id }}" class="accordion-collapse collapse show" data-bs-parent="#accordion-kesiswaan-{{ $r->id }}">
                        <div class="accordion-body">
                            <form method="POST" action="{{ route('kesiswaan.process',$r) }}" class="row g-3 align-items-end">@csrf
                                <div class="col-lg-6"><label class="form-label required">Siswa yang terbukti</label><select name="student_id" class="form-select" required><option value="">Pilih siswa</option>@foreach($students as $s)<option value="{{ $s->id }}">{{ $s->name }} - {{ $s->class?->class_name }}</option>@endforeach</select></div>
                                <div class="col-lg-6"><label class="form-label required">Jenis pelanggaran</label><select name="violation_type_id" class="form-select" required><option value="">Pilih jenis</option>@foreach($types as $t)<option value="{{ $t->id }}">{{ $t->violation_name }} (-{{ $t->point_reduction }} poin)</option>@endforeach</select></div>
                                <div class="col-12"><label class="form-label">Catatan pembinaan</label><textarea name="note" class="form-control" placeholder="Opsional" maxlength="2000" rows="3"></textarea></div>
                                <div class="col-12"><button class="btn btn-laporin">Proses Laporan</button></div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Reject Tab -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#reject-{{ $r->id }}" aria-expanded="false" aria-controls="reject-{{ $r->id }}">
                            Tolak Laporan
                        </button>
                    </h2>
                    <div id="reject-{{ $r->id }}" class="accordion-collapse collapse" data-bs-parent="#accordion-kesiswaan-{{ $r->id }}">
                        <div class="accordion-body">
                            <form method="POST" action="{{ route('kesiswaan.reject',$r) }}" class="row g-3" @submit="if(!confirm('Tolak laporan ini? Alur laporan akan berhenti.')) $event.preventDefault()">@csrf
                                <div class="col-12"><label class="form-label required">Alasan penolakan</label><textarea name="reason" class="form-control" placeholder="Wajib diisi jika laporan ditolak" required maxlength="2000" rows="3"></textarea></div>
                                <div class="col-12"><button class="btn btn-outline-danger">Tolak Laporan</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($r->status === 'sedang_ditangani')
            <form method="POST" action="{{ route('kesiswaan.complete', $r) }}" class="row g-3 align-items-end" @submit="if(!confirm('Tandai penanganan Kesiswaan selesai dan minta konfirmasi pelapor?')) $event.preventDefault()">
                @csrf
                <div class="col-12">
                    <label class="form-label" for="completion_note_{{ $r->id }}">Catatan penyelesaian Kesiswaan</label>
                    <textarea id="completion_note_{{ $r->id }}" name="note" class="form-control" maxlength="2000" placeholder="Ringkasan pembinaan atau tindak lanjut (opsional)" rows="3"></textarea>
                </div>
                <div class="col-12"><button class="btn btn-laporin">Selesaikan Penanganan</button></div>
            </form>
        @else
            <div class="status-note mb-0">
                Status laporan saat ini: <strong>{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</strong>.
                Tidak ada aksi Kesiswaan yang perlu dilakukan di tahap ini.
            </div>
        @endif
    </article>
@empty
    <div class="laporin-card text-center py-5 text-muted">Belum ada data.</div>
@endforelse
</div>
<div class="mt-3">{{ $reports->appends(request()->query())->links() }}</div>
@endsection

