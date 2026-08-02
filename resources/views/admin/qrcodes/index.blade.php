@extends('layouts.app')
@section('title','QR Code')
@section('content')
<div class="page-header"><div><span class="page-kicker">SuperAdmin</span><h1 class="page-title h2 mt-2">Manajemen QR Code</h1><p class="page-subtitle">Buat QR umum, kelas, atau lokasi dengan validasi relasi agar scan langsung membuka form laporan yang tepat.</p></div></div>
<div class="laporin-card mb-4" x-data="{type: @js(old('qr_type','general'))}">
    <h2 class="h5 fw-bold mb-3">Buat QR tervalidasi</h2>
    <form method="POST" action="{{ route('admin.qrcodes.store') }}" class="row g-3 align-items-end">
        @csrf
        <div class="col-md-4"><label class="form-label required" for="qr_name">Nama QR</label><input id="qr_name" name="qr_name" value="{{ old('qr_name') }}" class="form-control" placeholder="Contoh: QR Gerbang Utama" required maxlength="150" pattern="[A-Za-z0-9 ._\-()]+"></div>
        <div class="col-md-3"><label class="form-label required" for="qr_type">Tipe</label><select id="qr_type" name="qr_type" x-model="type" class="form-select" required><option value="general">Umum</option><option value="class">Kelas</option><option value="location">Lokasi</option></select></div>
        <div class="col-md-3" x-show="type==='class'" x-cloak><label class="form-label required" for="class_id">Kelas</label><select id="class_id" name="class_id" class="form-select" :required="type==='class'" :disabled="type!=='class'"><option value="">Pilih kelas</option>@foreach($classes as $c)<option value="{{ $c->id }}" @selected(old('class_id') == $c->id)>{{ $c->class_name }}</option>@endforeach</select></div>
        <div class="col-md-3" x-show="type==='location'" x-cloak><label class="form-label required" for="location_id">Lokasi</label><select id="location_id" name="location_id" class="form-select" :required="type==='location'" :disabled="type!=='location'"><option value="">Pilih lokasi</option>@foreach($locations as $l)<option value="{{ $l->id }}" @selected(old('location_id') == $l->id)>{{ $l->location_name }}</option>@endforeach</select></div>
        <div class="col-md-2"><button class="btn btn-laporin w-100">Buat QR</button></div>
    </form>
    <div class="helper-text">Backend menolak class_id untuk tipe non-kelas dan location_id untuk tipe non-lokasi.</div>
</div>
<div class="laporin-card">
    <div class="table-responsive"><table class="table"><thead><tr><th>Nama</th><th>Tipe</th><th>URL</th><th>Scan</th><th class="text-end">Aksi</th></tr></thead><tbody>
        @forelse($qrs as $q)<tr><td><strong>{{ $q->qr_name }}</strong></td><td>{{ $q->qr_type }}</td><td><code>{{ $q->target_url }}</code></td><td>{{ $q->scan_count }}</td><td class="text-end"><a class="btn btn-sm btn-outline-laporin" href="{{ route('admin.qrcodes.download',$q) }}">Download PNG</a><form class="d-inline" method="POST" action="{{ route('admin.qrcodes.deactivate',$q) }}" onsubmit="return confirm('Nonaktifkan QR ini?')">@csrf<button class="btn btn-sm btn-outline-danger" @disabled(! $q->is_active)>Nonaktif</button></form></td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Belum ada QR.</td></tr>@endforelse
    </tbody></table></div><div class="mt-3">{{ $qrs->links() }}</div>
</div>
@endsection
