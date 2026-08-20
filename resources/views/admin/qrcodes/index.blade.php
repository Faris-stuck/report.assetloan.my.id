@extends('layouts.app')

@section('title', 'Kode QR')

@section('content')

<div class="page-header">
    <div>
        <span class="page-kicker">
            SuperAdmin
        </span>

        <h1 class="page-title h2 mt-2">
            Manajemen Kode QR
        </h1>

        <p class="page-subtitle">
            Buat QR LAPORIN umum yang langsung membuka formulir laporan.
        </p>
    </div>
</div>

@if(session('status'))
    <div class="alert alert-success">
        {{ session('status') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Terjadi kesalahan.</strong>

        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ====================================================== --}}
{{-- CREATE QR --}}
{{-- ====================================================== --}}

<div class="laporin-card mb-4">

    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">

        <div>
            <h2 class="h5 fw-bold mb-1">
                Buat QR LAPORIN
            </h2>

            <p class="text-muted small mb-0">
                Semua QR yang dibuat pada halaman ini menggunakan tipe Umum.
            </p>
        </div>

        <span class="badge text-bg-success px-3 py-2">
            TIPE: UMUM
        </span>

    </div>

    <form
        method="POST"
        action="{{ route('admin.qrcodes.store') }}"
        class="row g-3 align-items-end"
    >

        @csrf

        <div class="col-md-8">

            <label
                class="form-label required"
                for="qr_name"
            >
                Nama QR
            </label>

            <input
                id="qr_name"
                name="qr_name"
                type="text"
                value="{{ old('qr_name') }}"
                class="form-control @error('qr_name') is-invalid @enderror"
                placeholder="Contoh: QR LAPORIN Utama"
                required
                maxlength="150"
                pattern="[A-Za-z0-9 ._\-()]+"
                autocomplete="off"
            >
            @error('qr_name')
                <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
            <div class="form-text">
                Gunakan nama yang mudah dikenali.
            </div>

        </div>

        <div class="col-md-2">

            <label class="form-label">
                Tipe
            </label>

            <input
                type="text"
                class="form-control"
                value="Umum"
                disabled
            >

        </div>

        <div class="col-md-2">

            <button
                type="submit"
                class="btn btn-laporin w-100"
            >
                Buat QR
            </button>

        </div>

    </form>

</div>

{{-- ====================================================== --}}
{{-- SEARCH --}}
{{-- ====================================================== --}}

<div class="laporin-card mb-4">

    <form
        method="GET"
        action="{{ route('admin.qrcodes.index') }}"
        class="row g-3 align-items-end"
    >

        <div class="col-md-7">

            <label
                class="form-label"
                for="search"
            >
                Cari QR
            </label>

            <input
                id="search"
                name="search"
                type="text"
                class="form-control"
                placeholder="Cari nama QR..."
                value="{{ request('search') }}"
                maxlength="100"
            >

        </div>

        <div class="col-md-3">

            <label
                class="form-label"
                for="status"
            >
                Status
            </label>

            <select
                id="status"
                name="status"
                class="form-select"
            >

                <option value="">
                    Semua Status
                </option>

                <option
                    value="active"
                    @selected(request('status') === 'active')
                >
                    Aktif
                </option>

                <option
                    value="inactive"
                    @selected(request('status') === 'inactive')
                >
                    Nonaktif
                </option>

            </select>

        </div>

        <div class="col-md-2 d-flex gap-2">

            <button
                type="submit"
                class="btn btn-laporin flex-grow-1"
            >
                Cari
            </button>

            <a
                href="{{ route('admin.qrcodes.index') }}"
                class="btn btn-outline-secondary"
            >
                Reset
            </a>

        </div>

    </form>

</div>

{{-- ====================================================== --}}
{{-- QR LIST --}}
{{-- ====================================================== --}}

<div class="laporin-card">

    @if(request('search') || request('status'))

        <div class="mb-3 pb-3 border-bottom">

            <p class="text-muted small mb-0">

                Menampilkan
                {{ $qrs->count() }}
                dari
                {{ $qrs->total() }}
                hasil

                @if(request('search'))

                    untuk pencarian

                    "<strong>
                        {{ request('search') }}
                    </strong>"

                @endif

            </p>

        </div>

    @endif

    {{-- DESKTOP TABLE --}}

    <div class="table-responsive d-none d-md-block">

        <table class="table align-middle">

            <thead>

                <tr>

                    <th>
                        Nama QR
                    </th>

                    <th>
                        URL
                    </th>

                    <th>
                        Scan
                    </th>

                    <th>
                        Status
                    </th>

                    <th class="text-end">
                        Aksi
                    </th>

                </tr>

            </thead>

            <tbody>

                @forelse($qrs as $q)

                    <tr>

                        <td>

                            <strong>
                                {{ $q->qr_name }}
                            </strong>

                            <div class="small text-muted">
                                Umum
                            </div>

                        </td>

                        <td>

                            <code class="small">
                                {{ $q->target_url }}
                            </code>

                        </td>

                        <td>

                            {{ $q->scan_count }}

                        </td>

                        <td>

                            <span
                                class="badge {{
                                    $q->is_active
                                        ? 'text-bg-success'
                                        : 'text-bg-secondary'
                                }}"
                            >

                                {{
                                    $q->is_active
                                        ? 'Aktif'
                                        : 'Nonaktif'
                                }}

                            </span>

                        </td>

                        <td class="text-end text-nowrap">

                            @if($q->is_active)

                                <form
                                    method="GET"
                                    action="{{ route('admin.qrcodes.download', $q) }}"
                                    class="d-inline-flex gap-1 align-items-center"
                                >
                                    <select
                                        name="paper"
                                        class="form-select form-select-sm"
                                        aria-label="Ukuran poster {{ $q->qr_name }}"
                                        style="min-width: 165px"
                                    >
                                        @foreach($posterSizes as $paperKey => $paperSize)

                                            <option
                                                value="{{ $paperKey }}"
                                                @selected(
                                                    $paperKey ===
                                                    $defaultPosterPaper
                                                )
                                            >
                                                {{ $paperSize['label'] }}
                                            </option>

                                        @endforeach
                                    </select>

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-laporin"
                                    >
                                        Unduh
                                    </button>
                                </form>

                            @else

                                <button
                                    class="btn btn-sm btn-outline-secondary"
                                    disabled
                                >
                                    Unduh Poster
                                </button>

                            @endif

                            <form
                                class="d-inline"
                                method="POST"
                                action="{{ route('admin.qrcodes.deactivate', $q) }}"
                                onsubmit="return confirm('Nonaktifkan QR ini?')"
                            >

                                @csrf

                                <button
                                    class="btn btn-sm btn-outline-danger"
                                    @disabled(! $q->is_active)
                                >
                                    Nonaktif
                                </button>

                            </form>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="5"
                            class="text-center text-muted py-5"
                        >

                            Belum ada QR LAPORIN.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>

    {{-- MOBILE CARDS --}}

    <div class="d-md-none">

        @forelse($qrs as $q)

            <div class="card mb-3">

                <div class="card-body">

                    <div class="d-flex justify-content-between gap-2 mb-2">

                        <div>

                            <h6 class="card-title mb-1">
                                {{ $q->qr_name }}
                            </h6>

                            <span class="badge text-bg-info">
                                Umum
                            </span>

                        </div>

                        <span
                            class="badge {{
                                $q->is_active
                                    ? 'text-bg-success'
                                    : 'text-bg-secondary'
                            }}"
                        >

                            {{
                                $q->is_active
                                    ? 'Aktif'
                                    : 'Nonaktif'
                            }}

                        </span>

                    </div>

                    <p class="text-muted small mb-2">

                        Scan:
                        {{ $q->scan_count }}

                    </p>

                    <div class="mb-3">

                        <code class="small text-break">
                            {{ $q->target_url }}
                        </code>

                    </div>

                    <div class="d-flex gap-2">

                        @if($q->is_active)

                            <form
                                method="GET"
                                action="{{ route('admin.qrcodes.download', $q) }}"
                                class="flex-grow-1"
                            >
                                <div class="d-grid gap-2">

                                    <select
                                        name="paper"
                                        class="form-select form-select-sm"
                                        aria-label="Ukuran poster {{ $q->qr_name }}"
                                    >
                                        @foreach($posterSizes as $paperKey => $paperSize)

                                            <option
                                                value="{{ $paperKey }}"
                                                @selected(
                                                    $paperKey ===
                                                    $defaultPosterPaper
                                                )
                                            >
                                                {{ $paperSize['label'] }}
                                            </option>

                                        @endforeach
                                    </select>

                                    <button
                                        type="submit"
                                        class="btn btn-sm btn-outline-laporin"
                                    >
                                        Unduh Poster
                                    </button>

                                </div>
                            </form>

                        @else

                            <button
                                class="btn btn-sm btn-outline-secondary flex-grow-1"
                                disabled
                            >
                                Unduh Poster
                            </button>

                        @endif

                        <form
                            method="POST"
                            action="{{ route('admin.qrcodes.deactivate', $q) }}"
                            class="flex-grow-1"
                            onsubmit="return confirm('Nonaktifkan QR ini?')"
                        >

                            @csrf

                            <button
                                class="btn btn-sm btn-outline-danger w-100"
                                @disabled(! $q->is_active)
                            >
                                Nonaktif
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        @empty

            <div class="alert alert-info mb-0">
                Belum ada QR LAPORIN.
            </div>

        @endforelse

    </div>

    <div class="mt-3">

        {{
            $qrs
                ->appends(request()->query())
                ->links()
        }}

    </div>

</div>

@endsection
