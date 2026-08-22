@extends('layouts.app')

@section('title', 'Detail Laporan')

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
        'diverifikasi' => 'Sudah Diverifikasi',
        'ditugaskan' => 'Ditugaskan',
        'dibuka_kembali' => 'Dibuka Kembali',
        'sedang_ditangani' => 'Sedang Ditangani',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi Pelapor',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
        'diarsipkan' => 'Diarsipkan',
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

    {{-- ========================================================= --}}
    {{-- HEADER LAPORAN                                            --}}
    {{-- ========================================================= --}}
    <div class="page-header mb-4">
        <div>
            <span class="page-kicker">Detail laporan</span>

            <h1 class="page-title h3 mt-2">
                {{ $report->report_number }}
            </h1>

            <p class="page-subtitle">
                {{ $report->title }}
            </p>
        </div>

        <span class="status-pill status-{{ $report->status }}">
            {{ $statusLabels[$report->status] ?? ucwords(str_replace('_', ' ', $report->status)) }}
        </span>
    </div>


    {{-- ========================================================= --}}
    {{-- ALUR STATUS                                               --}}
    {{-- ========================================================= --}}
    <div class="flowchart compact mb-4">
        @foreach($flow as $status => $label)
            <div class="flow-node {{ $report->status === $status ? 'is-active' : '' }}">
                {{ $label }}
            </div>
        @endforeach
    </div>


    {{-- ========================================================= --}}
    {{-- INFORMASI UMUM                                            --}}
    {{-- ========================================================= --}}
    <div class="row g-3 mb-4">

        <div class="col-md-6">
            <div class="detail-box h-100">
                <div class="small-muted">
                    Pelapor
                </div>

                <strong>
                    {{ $report->reporter_name }}
                </strong>

                <div>
                    {{ ucfirst($report->reporter_type) }}
                </div>
            </div>
        </div>


        <div class="col-md-6">
            <div class="detail-box h-100">
                <div class="small-muted">
                    Jenis Laporan
                </div>

                <strong>
                    {{ $report->report_type === 'violation'
                        ? 'Pelanggaran siswa / perundungan'
                        : 'Kerusakan fasilitas'
                    }}
                </strong>
            </div>
        </div>


        <div class="col-md-6">
            <div class="detail-box h-100">
                <div class="small-muted">
                    Waktu Kejadian
                </div>

                <strong>
                    {{ $report->incident_date?->format('d/m/Y') ?? '-' }}
                </strong>

                @if($report->incident_time)
                    <div>
                        {{ $report->incident_time }}
                    </div>
                @endif
            </div>
        </div>


    </div>


    {{-- ========================================================= --}}
    {{-- DETAIL KHUSUS KERUSAKAN                                   --}}
    {{-- ========================================================= --}}
    @if($report->report_type === 'damage')

        <div class="mb-4">

            <h2 class="h5 fw-bold mb-3">
                Detail Kerusakan Fasilitas
            </h2>

            <div class="row g-3">

                <div class="col-md-6">
                    <div class="detail-box h-100">
                        <div class="small-muted">
                            Nama Barang / Fasilitas
                        </div>

                        <strong>
                            {{ $report->damageDetail?->item_name ?? '-' }}
                        </strong>
                    </div>
                </div>


                <div class="col-md-6">
                    <div class="detail-box h-100">
                        <div class="small-muted">
                            Deskripsi Kerusakan / Dampak
                        </div>

                        <div>
                            {{ $report->damageDetail?->damage_condition ?? '-' }}
                        </div>
                    </div>
                </div>


                @if($report->damageDetail?->item_category)
                    <div class="col-md-6">
                        <div class="detail-box h-100">
                            <div class="small-muted">
                                Kategori Barang
                            </div>

                            <strong>
                                {{ $report->damageDetail->item_category }}
                            </strong>
                        </div>
                    </div>
                @endif


                @if($report->damageDetail?->suspected_cause)
                    <div class="col-md-6">
                        <div class="detail-box h-100">
                            <div class="small-muted">
                                Dugaan Penyebab
                            </div>

                            <div>
                                {{ $report->damageDetail->suspected_cause }}
                            </div>
                        </div>
                    </div>
                @endif


                @if($report->damageDetail?->priority)
                    <div class="col-md-6">
                        <div class="detail-box h-100">

                            <div class="small-muted">
                                Prioritas
                            </div>

                            @php
                                $priorityClass = match ($report->damageDetail->priority) {
                                    'rendah' => 'text-bg-secondary',
                                    'sedang' => 'text-bg-warning',
                                    'tinggi' => 'text-bg-danger',
                                    'darurat' => 'text-bg-danger',
                                    default => 'text-bg-secondary',
                                };
                            @endphp

                            <span class="badge {{ $priorityClass }}">
                                {{ ucfirst($report->damageDetail->priority) }}
                            </span>

                        </div>
                    </div>
                @endif


                @if($report->damageDetail?->scheduled_repair_at)
                    <div class="col-md-6">
                        <div class="detail-box h-100">
                            <div class="small-muted">
                                Jadwal Perbaikan
                            </div>

                            <strong>
                                {{ $report->damageDetail->scheduled_repair_at->format('d/m/Y H:i') }}
                            </strong>
                        </div>
                    </div>
                @endif


                @if($report->damageDetail?->repaired_at)
                    <div class="col-md-6">
                        <div class="detail-box h-100">
                            <div class="small-muted">
                                Selesai Diperbaiki
                            </div>

                            <strong>
                                {{ $report->damageDetail->repaired_at->format('d/m/Y H:i') }}
                            </strong>
                        </div>
                    </div>
                @endif

            </div>
        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- DETAIL KHUSUS PELANGGARAN / PERUNDUNGAN                  --}}
    {{-- ========================================================= --}}
    @if($report->report_type === 'violation')

        <div class="mb-4">

            <h2 class="h5 fw-bold mb-3">
                Detail Pelanggaran / Perundungan
            </h2>

            <div class="row g-3">

                <div class="col-md-6">
                    <div class="detail-box h-100">
                        <div class="small-muted">
                            Nama Terduga Pelaku
                        </div>

                        <strong>
                            {{ $report->bullyingDetail?->alleged_actor_name ?? '-' }}
                        </strong>
                    </div>
                </div>


                @if($report->bullyingDetail?->allegedActorClass)
                    <div class="col-md-6">
                        <div class="detail-box h-100">
                            <div class="small-muted">
                                Kelas Terduga Pelaku
                            </div>

                            <strong>
                                {{ $report->bullyingDetail->allegedActorClass->class_name }}
                            </strong>
                        </div>
                    </div>
                @endif


                @if($report->relatedClass)
                    <div class="col-md-6">
                        <div class="detail-box h-100">
                            <div class="small-muted">
                                Kelas Terkait
                            </div>

                            <strong>
                                {{ $report->relatedClass->class_name }}
                            </strong>
                        </div>
                    </div>
                @endif


                @if($report->bullyingDetail?->victim_name)
                    <div class="col-md-6">
                        <div class="detail-box h-100">
                            <div class="small-muted">
                                Nama Korban
                            </div>

                            <strong>
                                {{ $report->bullyingDetail->victim_name }}
                            </strong>
                        </div>
                    </div>
                @endif


                @if($report->bullyingDetail?->witness_name)
                    <div class="col-md-6">
                        <div class="detail-box h-100">
                            <div class="small-muted">
                                Saksi
                            </div>

                            <strong>
                                {{ $report->bullyingDetail->witness_name }}
                            </strong>
                        </div>
                    </div>
                @endif


                @if($report->bullyingDetail?->bullying_type)
                    <div class="col-md-6">
                        <div class="detail-box h-100">
                            <div class="small-muted">
                                Jenis Perundungan
                            </div>

                            <strong>
                                {{ $report->bullyingDetail->bullying_type }}
                            </strong>
                        </div>
                    </div>
                @endif


                @if($report->bullyingDetail?->impact_description)
                    <div class="col-12">
                        <div class="detail-box">
                            <div class="small-muted">
                                Dampak
                            </div>

                            <div>
                                {{ $report->bullyingDetail->impact_description }}
                            </div>
                        </div>
                    </div>
                @endif

            </div>
        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- KRONOLOGI / DESKRIPSI                                     --}}
    {{-- ========================================================= --}}
    <div class="detail-box mb-4">

        <div class="small-muted mb-1">
            Kronologi / Deskripsi
        </div>

        <p class="mb-0">
            {{ $report->description }}
        </p>

    </div>


    {{-- ========================================================= --}}
    {{-- LAMPIRAN                                                  --}}
    {{-- ========================================================= --}}
    @if($report->attachments->isNotEmpty())

        <h2 class="h5 fw-bold mt-3 mb-3">
            Lampiran Aman
        </h2>

        <div class="report-card-list mb-4">

            @foreach($report->attachments as $attachment)

                <div class="report-row-card d-flex justify-content-between align-items-center flex-wrap gap-2">

                    <a href="{{ route('attachments.download', $attachment) }}">
                        {{ $attachment->original_name }}
                    </a>

                    <span class="small-muted">
                        {{ number_format($attachment->file_size / 1024, 1) }} KB
                    </span>

                </div>

            @endforeach

        </div>

    @endif


    {{-- ========================================================= --}}
    {{-- CATATAN LAPORAN                                           --}}
    {{-- ========================================================= --}}
    <h2 class="h5 fw-bold mt-3">
        Catatan Laporan
    </h2>

    <ul class="list-group mb-3">

        @forelse($report->notes as $note)

            <li class="list-group-item">

                <div class="mb-1">
                    <strong>
                        {{ $roleLabels[$note->author_type]
                            ?? ucfirst(str_replace('_', ' ', $note->author_type))
                        }}
                    </strong>

                    <span class="text-muted">
                        ·
                        {{ $visibilityLabels[$note->visibility]
                            ?? $note->visibility
                        }}
                    </span>
                </div>

                <div>
                    {{ $note->note }}
                </div>

                @if($note->created_at)
                    <div class="small-muted mt-1">
                        {{ $note->created_at->format('d/m/Y H:i') }}
                    </div>
                @endif

            </li>

        @empty

            <li class="list-group-item text-muted">
                Belum ada catatan.
            </li>

        @endforelse

    </ul>


    {{-- ========================================================= --}}
    {{-- TAMBAH CATATAN                                            --}}
    {{-- ========================================================= --}}
    @can('comment', $report)

        <form
            method="POST"
            action="{{ route('reports.notes', $report) }}"
            class="detail-box"
        >

            @csrf

            <label
                class="form-label required"
                for="note"
            >
                Tambah Catatan
            </label>

            <textarea
                id="note"
                name="note"
                class="form-control mb-2"
                required
                maxlength="3000"
                rows="4"
                placeholder="Tulis catatan tindak lanjut."
            >{{ old('note') }}</textarea>


            <label
                class="form-label"
                for="visibility"
            >
                Visibilitas Catatan
            </label>

            <select
                id="visibility"
                name="visibility"
                class="form-select mb-3"
                required
            >

                <option
                    value="internal"
                    @selected(old('visibility', 'internal') === 'internal')
                >
                    Catatan internal petugas
                </option>

                <option
                    value="reporter_visible"
                    @selected(old('visibility') === 'reporter_visible')
                >
                    Bisa dilihat pelapor
                </option>

            </select>


            <button
                type="submit"
                class="btn btn-outline-laporin"
            >
                Simpan Catatan
            </button>

        </form>

    @endcan

</div>
@endsection