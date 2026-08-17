@extends('layouts.app')
@section('title','Catatan Audit')
@section('content')
<div class="page-header">
    <div>
        <span class="page-kicker">SuperAdmin</span>
        <h1 class="page-title h2 mt-2">Catatan Audit</h1>
        <p class="page-subtitle">Pantau aktivitas pengguna dan perubahan data untuk keamanan dan akuntabilitas sistem.</p>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('admin.audit') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Cari nama, email, aktor, atau aksi..." value="{{ request('search') }}" maxlength="100">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="action">Aksi</label>
            <select id="action" name="action" class="form-select">
                <option value="">Semua</option>
                @foreach($actions as $act)
                    <option value="{{ $act }}" @selected(request('action') === $act)>{{ $act }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="from_date">Dari</label>
            <input id="from_date" name="from_date" type="date" class="form-control"
                   value="{{ request('from_date') }}">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="to_date">Sampai</label>
            <input id="to_date" name="to_date" type="date" class="form-control"
                   value="{{ request('to_date') }}">
        </div>

        <div class="col-md-6 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
            <a href="{{ route('admin.audit') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Audit Table -->
<div class="laporin-card">
    <!-- Results Info -->
    @if(request('search') || request('action') || request('from_date') || request('to_date'))
        <div class="mb-3 pb-3 border-bottom">
            <p class="text-muted small mb-0">
                Menampilkan {{ $logs->count() }} dari {{ $logs->total() }} hasil
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
                    <th>Waktu</th>
                    <th>Aktor</th>
                    <th>Aksi</th>
                    <th>Model</th>
                    <th>ID</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td><small>{{ $log->created_at->format('d M Y H:i:s') }}</small></td>
                        <td><strong>{{ $log->user?->name ?? $log->actor_type }}</strong>@if($log->user?->email)<div class="small text-muted">{{ $log->user->email }}</div>@endif</td>
                        <td><span class="badge text-bg-info">{{ $log->action }}</span></td>
                        <td>{{ $log->model_type }}</td>
                        <td>#{{ $log->model_id }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Belum ada data.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- MOBILE: Card View -->
    <div class="d-md-none">
        @forelse($logs as $log)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge text-bg-info">{{ $log->action }}</span>
                        <small class="text-muted">{{ $log->created_at->format('d M H:i') }}</small>
                    </div>
                    <p class="small mb-1"><strong>{{ $log->user?->name ?? $log->actor_type }}</strong></p>@if($log->user?->email)<p class="text-muted small mb-1">{{ $log->user->email }}</p>@endif
                    <p class="text-muted small mb-0">{{ $log->model_type }} #{{ $log->model_id }}</p>
                </div>
            </div>
        @empty
            <div class="alert alert-info">Belum ada data</div>
        @endforelse
    </div>

    <!-- Pagination with preserved filters -->
    <div class="mt-3">{{ $logs->appends(request()->query())->links() }}</div>
</div>
@endsection
