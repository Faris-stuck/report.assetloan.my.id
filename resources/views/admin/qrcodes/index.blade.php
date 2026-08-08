@extends('layouts.app')
@section('title','Kode QR')
@section('content')
<div class="page-header"><div><span class="page-kicker">SuperAdmin</span><h1 class="page-title h2 mt-2">Manajemen Kode QR</h1><p class="page-subtitle">Buat kode QR umum, kelas, atau lokasi dengan validasi relasi agar scan langsung membuka form laporan yang tepat.</p></div></div>

<!-- Create QR Card -->
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
    <small class="text-muted">Backend menolak class_id untuk tipe non-kelas dan location_id untuk tipe non-lokasi.</small>
</div>

<!-- Search & Filter Card -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('admin.qrcodes.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Cari nama atau email..." value="{{ request('search') }}" maxlength="100">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="type">Tipe</label>
            <select id="type" name="type" class="form-select">
                <option value="">Semua</option>
                <option value="general" @selected(request('type') === 'general')>Umum</option>
                <option value="class" @selected(request('type') === 'class')>Kelas</option>
                <option value="location" @selected(request('type') === 'location')>Lokasi</option>
            </select>
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                <option value="active" @selected(request('status') === 'active')>Aktif</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
            </select>
        </div>

        <div class="col-md-6 col-lg-4 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
            <a href="{{ route('admin.qrcodes.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- List with Search/Filter -->
<div class="laporin-card">
    <!-- Results Info -->
    @if(request('search') || request('type') || request('status'))
        <div class="mb-3 pb-3 border-bottom">
            <p class="text-muted small mb-0">
                Menampilkan {{ $qrs->count() }} dari {{ $qrs->total() }} hasil
                @if(request('search'))
                    untuk pencarian "<strong>{{ request('search') }}</strong>"
                @endif
            </p>
        </div>
    @endif

    <div class="table-responsive d-none d-md-block">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Tipe</th>
                    <th>URL</th>
                    <th>Scan</th>
                    <th>Status</th>
                    <th class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($qrs as $q)
                    <tr>
                        <td><strong>{{ $q->qr_name }}</strong></td>
                        <td><span class="badge text-bg-info">{{ $q->qr_type }}</span></td>
                        <td><code class="small">{{ $q->target_url }}</code></td>
                        <td>{{ $q->scan_count }}</td>
                        <td><span class="badge {{ $q->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $q->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-laporin" href="{{ route('admin.qrcodes.download',$q) }}" aria-label="Download kode QR {{ $q->qr_name }}">Unduh</a>
                            <form class="d-inline" method="POST" action="{{ route('admin.qrcodes.deactivate',$q) }}" onsubmit="return confirm('Nonaktifkan QR ini?')">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger" aria-label="Nonaktifkan kode QR {{ $q->qr_name }}" @disabled(! $q->is_active)>Nonaktif</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- MOBILE: Card View -->
    <div class="d-md-none">
        @forelse($qrs as $q)
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="card-title">{{ $q->qr_name }}</h6>
                    <div class="d-flex gap-2 justify-content-between align-items-center mb-2">
                        <span class="badge text-bg-info">{{ $q->qr_type }}</span>
                        <span class="badge {{ $q->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                            {{ $q->is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                    <p class="text-muted small mb-2">Scan: {{ $q->scan_count }}</p>
                    <div class="d-flex gap-2">
                        <a class="btn btn-sm btn-outline-laporin flex-grow-1" href="{{ route('admin.qrcodes.download',$q) }}" aria-label="Download kode QR {{ $q->qr_name }}">Unduh</a>
                        <form method="POST" action="{{ route('admin.qrcodes.deactivate',$q) }}" onsubmit="return confirm('Nonaktifkan QR?')" class="flex-grow-1">
                            @csrf
                            <button class="btn btn-sm btn-outline-danger w-100" aria-label="Nonaktifkan kode QR {{ $q->qr_name }}" @disabled(! $q->is_active)>Nonaktif</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="alert alert-info">Belum ada data</div>
        @endforelse
    </div>

    <!-- Pagination with preserved filters -->
    <div class="mt-3">{{ $qrs->appends(request()->query())->links() }}</div>
</div>
@endsection
