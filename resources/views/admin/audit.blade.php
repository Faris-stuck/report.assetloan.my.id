@extends('layouts.app')
@section('title','Catatan Audit')
@section('content')
@php
    // Dipakai untuk membedakan "log memang kosong" dari "filter terlalu sempit".
    // Tanpa pembeda ini keduanya sama-sama berbunyi "Belum ada data." sehingga
    // operator tidak tahu apakah harus melebarkan filter atau memang belum ada
    // aktivitas yang tercatat.
    $hasActiveFilter = request('search') || request('action') || request('from_date') || request('to_date');
@endphp
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
            {{-- max/min saling mengunci supaya rentang terbalik (Dari lebih baru
                 daripada Sampai) ditolak browser sejak awal. Sebelumnya rentang
                 terbalik lolos, query mengembalikan nol baris, dan operator
                 hanya melihat "Belum ada data." tanpa tahu rentangnyalah yang
                 mustahil. --}}
            <input id="from_date" name="from_date" type="date" class="form-control"
                   value="{{ request('from_date') }}"
                   @if(request('to_date')) max="{{ request('to_date') }}" @endif
                   data-range-start>
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="to_date">Sampai</label>
            <input id="to_date" name="to_date" type="date" class="form-control"
                   value="{{ request('to_date') }}"
                   @if(request('from_date')) min="{{ request('from_date') }}" @endif
                   data-range-end>
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
    @if($hasActiveFilter)
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
                        {{-- model_type disimpan sebagai nama kelas berkualifikasi
                             penuh (App\Models\Report). Yang ditampilkan cukup
                             nama kelasnya; nama lengkap tetap tersedia di title
                             karena ini catatan audit dan jejaknya harus utuh. --}}
                        <td><span title="{{ $log->model_type }}">{{ class_basename($log->model_type) }}</span></td>
                        <td>{{ $log->model_id ? '#'.$log->model_id : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            @if($hasActiveFilter)
                                Tidak ada aktivitas yang cocok dengan filter ini.
                                <a href="{{ route('admin.audit') }}">Reset filter</a> untuk melihat seluruh catatan.
                            @else
                                Belum ada aktivitas yang tercatat.
                            @endif
                        </td>
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
                    <p class="text-muted small mb-0"><span title="{{ $log->model_type }}">{{ class_basename($log->model_type) }}</span> {{ $log->model_id ? '#'.$log->model_id : '' }}</p>
                </div>
            </div>
        @empty
            <div class="alert alert-info">
                @if($hasActiveFilter)
                    Tidak ada aktivitas yang cocok dengan filter ini.
                    <a href="{{ route('admin.audit') }}" class="alert-link">Reset filter</a> untuk melihat seluruh catatan.
                @else
                    Belum ada aktivitas yang tercatat.
                @endif
            </div>
        @endforelse
    </div>

    <!-- Pagination with preserved filters -->
    <div class="mt-3">{{ $logs->appends(request()->query())->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
    // Atribut min/max yang dirender server hanya mencerminkan nilai request
    // SEBELUMNYA, jadi rentang terbalik masih bisa disusun sebelum submit
    // pertama. Skrip ini mengunci keduanya secara langsung supaya operator
    // mendapat penolakan browser saat memilih tanggal, bukan setelah menunggu
    // halaman kembali dengan nol hasil.
    document.addEventListener('DOMContentLoaded', () => {
        const start = document.querySelector('[data-range-start]');
        const end = document.querySelector('[data-range-end]');

        if (! start || ! end) {
            return;
        }

        const sync = () => {
            start.max = end.value || '';
            end.min = start.value || '';
        };

        start.addEventListener('change', sync);
        end.addEventListener('change', sync);
        sync();
    });
</script>
@endpush
