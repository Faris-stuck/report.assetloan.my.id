@extends('layouts.app')

@section('title', 'Master Data')

@section('content')

@php
    $labels = [
        'classes' => 'Kelas',
        'subjects' => 'Mata Pelajaran',
        'staff-units' => 'Unit Staf',
        'locations' => 'Lokasi',
        'violation-types' => 'Jenis Pelanggaran',
        'damage-categories' => 'Kategori Kerusakan',
    ];

    $fieldLabels = [
        'class_name' => 'Nama Kelas',
        'grade_level' => 'Tingkat',
        'major' => 'Jurusan',
        'academic_year' => 'Tahun Ajaran',
        'room_name' => 'Nama Ruangan',
        'location_name' => 'Nama Lokasi',
        'location_type' => 'Jenis Lokasi',
        'subject_name' => 'Nama Mata Pelajaran',
        'unit_name' => 'Nama Unit',
        'violation_name' => 'Jenis Pelanggaran',
        'category_name' => 'Nama Kategori',
        'description' => 'Deskripsi',
        'is_active' => 'Status',
        'class_id' => 'Kelas',
        'point_reduction' => 'Pengurangan Poin',
    ];

    $required = [
        'classes' => [
            'class_name',
            'grade_level',
            'academic_year',
        ],

        'subjects' => [
            'subject_name',
        ],

        'staff-units' => [
            'unit_name',
        ],

        'locations' => [
            'location_name',
        ],

        'violation-types' => [
            'violation_name',
            'point_reduction',
        ],

        'damage-categories' => [
            'category_name',
        ],
    ][$resource] ?? [];

    $inputMax = function (string $field): int {
        if (
            in_array(
                $field,
                [
                    'class_name',
                    'grade_level',
                    'major',
                    'academic_year',
                    'room_name',
                    'location_type',
                ],
                true
            )
        ) {
            return 80;
        }

        return 150;
    };

    $labelFor = function (string $field) use ($fieldLabels): string {
        return $fieldLabels[$field]
            ?? ucwords(str_replace('_', ' ', $field));
    };

    // Nama manusiawi sebuah baris. Field penamaan berbeda tiap resource
    // (class_name, subject_name, unit_name, ...), jadi ambil field non-meta
    // pertama yang terisi. Dipakai judul kartu mobile dan aria-label aksi
    // supaya pembaca layar tidak hanya mendengar nomor id.
    $displayName = function ($item) use ($fields): string {
        foreach ($fields as $field) {
            if (
                !in_array(
                    $field,
                    [
                        'description',
                        'is_active',
                        'class_id',
                    ],
                    true
                )
                && !empty($item->$field)
            ) {
                return (string) $item->$field;
            }
        }

        return 'Data #'.$item->id;
    };
@endphp


{{-- ============================================================
     HEADER
     ============================================================ --}}

<div class="page-header">

    <div>

        <span class="page-kicker">
            Master Data
        </span>

        <h1 class="page-title h2 mt-2">
            {{ $labels[$resource] ?? $resource }}
        </h1>

        <p class="page-subtitle">
            Kelola data referensi yang digunakan pada form laporan,
            QR Code, dan proses petugas.
        </p>

    </div>

</div>


{{-- ============================================================
     CREATE DATA
     ============================================================ --}}

<div class="laporin-card mb-4">

    <h2 class="h5 fw-bold mb-3">
        Tambah Data
    </h2>

    <form
        method="POST"
        action="{{ route('admin.master.store', $resource) }}"
        class="row g-3 align-items-end"
    >

        @csrf


        @foreach($fields as $f)

            {{-- STATUS AKTIF --}}
            @if($f === 'is_active')

                <div class="col-md-3">

                    <div class="form-check form-switch mt-4">

                        <input
                            id="create_is_active"
                            class="form-check-input"
                            type="checkbox"
                            name="is_active"
                            value="1"
                            checked
                        >

                        <label
                            for="create_is_active"
                            class="form-check-label"
                        >
                            Data Aktif
                        </label>

                    </div>

                </div>


            {{-- CLASS ID --}}
            @elseif($f === 'class_id')

                <div class="col-md-4">

                    <label
                        class="form-label"
                        for="create_class_id"
                    >
                        Kelas
                    </label>

                    <select
                        id="create_class_id"
                        name="class_id"
                        class="form-select"
                    >

                        <option value="">
                            Tidak terkait kelas
                        </option>

                        @foreach($classes as $c)

                            <option
                                value="{{ $c->id }}"
                                @selected(old('class_id') == $c->id)
                            >
                                {{ $c->class_name }}
                            </option>

                        @endforeach

                    </select>

                </div>


            {{-- POINT REDUCTION --}}
            @elseif($f === 'point_reduction')

                <div class="col-md-3">

                    <label
                        class="form-label required"
                        for="create_point_reduction"
                    >
                        Pengurangan Poin
                    </label>

                    <input
                        id="create_point_reduction"
                        type="number"
                        name="point_reduction"
                        class="form-control"
                        required
                        min="1"
                        max="100"
                        value="{{ old('point_reduction') }}"
                    >

                </div>


            {{-- DESCRIPTION --}}
            @elseif($f === 'description')

                <div class="col-md-5">

                    <label
                        class="form-label"
                        for="create_description"
                    >
                        Deskripsi
                    </label>

                    <textarea
                        id="create_description"
                        name="description"
                        class="form-control"
                        maxlength="1000"
                        rows="2"
                        placeholder="Opsional"
                    >{{ old('description') }}</textarea>

                </div>


            {{-- FIELD BIASA --}}
            @else

                @php
                    $isRequired = in_array($f, $required, true);
                @endphp

                <div class="col-md-4">

                    <label
                        class="form-label {{ $isRequired ? 'required' : '' }}"
                        for="create_{{ $f }}"
                    >
                        {{ $labelFor($f) }}
                    </label>

                    <input
                        id="create_{{ $f }}"
                        name="{{ $f }}"
                        type="text"
                        class="form-control"
                        placeholder="{{ $labelFor($f) }}"
                        value="{{ old($f) }}"
                        maxlength="{{ $inputMax($f) }}"
                        @required($isRequired)
                    >

                </div>

            @endif

        @endforeach


        <div class="col-md-2">

            <button
                type="submit"
                class="btn btn-laporin w-100"
            >
                Tambah
            </button>

        </div>

    </form>

</div>


{{-- ============================================================
     SEARCH & FILTER
     ============================================================ --}}

<div class="laporin-card mb-4">

    <form
        method="GET"
        action="{{ route('admin.master.index', $resource) }}"
        class="row g-3 align-items-end"
    >

        <div class="col-md-6 col-lg-5">

            <label
                class="form-label"
                for="search"
            >
                Cari
            </label>

            <input
                id="search"
                name="search"
                type="text"
                class="form-control"
                placeholder="Cari data..."
                value="{{ request('search') }}"
                maxlength="100"
            >

        </div>


        <div class="col-md-6 col-lg-3">

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
                    Semua
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


        <div class="col-md-6 col-lg-4 d-flex gap-2">

            <button
                type="submit"
                class="btn btn-laporin flex-grow-1"
            >
                Cari
            </button>

            <a
                href="{{ route('admin.master.index', $resource) }}"
                class="btn btn-outline-secondary"
            >
                Reset
            </a>

        </div>

    </form>

</div>


{{-- ============================================================
     MAIN CARD
     ============================================================ --}}

<div class="laporin-card">


    {{-- ========================================================
         RESULT INFO
         ======================================================== --}}

    @if(request('search') || request('status'))

        <div class="mb-3 pb-3 border-bottom">

            <p class="text-muted small mb-0">

                Menampilkan

                <strong>
                    {{ $items->count() }}
                </strong>

                dari

                <strong>
                    {{ $items->total() }}
                </strong>

                hasil.

                @if(request('search'))

                    Pencarian:

                    <strong>
                        "{{ request('search') }}"
                    </strong>

                @endif

            </p>

        </div>

    @endif


    {{-- ========================================================
         DESKTOP TABLE
         ======================================================== --}}

    <div class="table-responsive d-none d-md-block">

        <table class="table align-middle">

            <thead>

                <tr>

                    @foreach($fields as $f)

                        <th>
                            {{ $labelFor($f) }}
                        </th>

                    @endforeach

                    <th class="text-end">
                        Aksi
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse($items as $it)

                    <tr>

                        @foreach($fields as $f)

                            <td>

                                @if($f === 'is_active')

                                    <span
                                        class="badge {{ $it->$f ? 'text-bg-success' : 'text-bg-secondary' }}"
                                    >
                                        {{ $it->$f ? 'Aktif' : 'Nonaktif' }}
                                    </span>


                                @elseif($f === 'class_id')

                                    {{ $it->class?->class_name ?? 'Tidak terkait' }}


                                @elseif($f === 'description')

                                    @if($it->$f)

                                        <span
                                            class="text-muted small"
                                            title="{{ $it->$f }}"
                                        >
                                            {{ \Illuminate\Support\Str::limit($it->$f, 50) }}
                                        </span>

                                    @else

                                        -

                                    @endif


                                @else

                                    {{ $it->$f ?? '-' }}

                                @endif

                            </td>

                        @endforeach


                        <td class="text-end text-nowrap">


                            {{-- EDIT --}}
                            <button
                                type="button"
                                class="btn btn-sm btn-outline-laporin"
                                data-bs-toggle="modal"
                                data-bs-target="#edit-master-{{ $it->id }}"
                                aria-label="Edit data {{ $displayName($it) }}"
                            >
                                Edit
                            </button>


                            {{-- DELETE --}}
                            <form
                                method="POST"
                                action="{{ route('admin.master.destroy', [$resource, $it->id]) }}"
                                style="display:inline"
                                onsubmit="return confirm('Yakin ingin menghapus data ini?')"
                            >

                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="btn btn-sm btn-outline-danger"
                                    aria-label="Hapus data {{ $displayName($it) }}"
                                >
                                    Hapus
                                </button>

                            </form>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="{{ count($fields) + 1 }}"
                            class="text-center text-muted py-4"
                        >
                            Belum ada data.
                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    {{-- ========================================================
         MOBILE CARD VIEW
         ======================================================== --}}

    <div class="d-md-none">

        @forelse($items as $it)

            <div class="card mb-3">

                <div class="card-body">


                    {{-- TITLE --}}
                    <h6 class="card-title fw-bold">

                        {{ $displayName($it) }}

                    </h6>


                    {{-- DETAIL --}}
                    <div class="small mb-3">

                        @foreach($fields as $f)

                            @if($f === 'is_active')

                                <div class="mb-1">

                                    <strong>
                                        Status:
                                    </strong>

                                    <span
                                        class="badge {{ $it->$f ? 'text-bg-success' : 'text-bg-secondary' }}"
                                    >
                                        {{ $it->$f ? 'Aktif' : 'Nonaktif' }}
                                    </span>

                                </div>


                            @elseif($f === 'class_id')

                                <div class="mb-1">

                                    <strong>
                                        {{ $labelFor($f) }}:
                                    </strong>

                                    {{ $it->class?->class_name ?? 'Tidak terkait' }}

                                </div>


                            @elseif($f === 'description')

                                @if($it->$f)

                                    <div class="mb-1">

                                        <strong>
                                            {{ $labelFor($f) }}:
                                        </strong>

                                        {{ \Illuminate\Support\Str::limit($it->$f, 100) }}

                                    </div>

                                @endif


                            @else

                                <div class="mb-1">

                                    <strong>
                                        {{ $labelFor($f) }}:
                                    </strong>

                                    {{ $it->$f ?? '-' }}

                                </div>

                            @endif

                        @endforeach

                    </div>


                    {{-- ACTIONS --}}
                    <div class="d-flex gap-2">

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-laporin flex-grow-1"
                            data-bs-toggle="modal"
                            data-bs-target="#edit-master-{{ $it->id }}"
                            aria-label="Edit data {{ $displayName($it) }}"
                        >
                            Edit
                        </button>


                        <form
                            method="POST"
                            action="{{ route('admin.master.destroy', [$resource, $it->id]) }}"
                            onsubmit="return confirm('Yakin ingin menghapus data ini?')"
                            class="flex-grow-1"
                        >

                            @csrf
                            @method('DELETE')

                            <button
                                type="submit"
                                class="btn btn-sm btn-outline-danger w-100"
                                aria-label="Hapus data {{ $displayName($it) }}"
                            >
                                Hapus
                            </button>

                        </form>

                    </div>

                </div>

            </div>

        @empty

            <div class="alert alert-info">
                Belum ada data.
            </div>

        @endforelse

    </div>


    {{-- ========================================================
         PAGINATION
         ======================================================== --}}

    <div class="mt-3">

        {{ $items->appends(request()->query())->links() }}

    </div>

</div>


{{-- ============================================================
     BOOTSTRAP EDIT MODALS
     SATU MODAL PER ITEM
     ============================================================ --}}

@foreach($items as $it)

    @php
        // Hanya baris yang benar-benar disubmit boleh memakai old(). Tanpa
        // penanda ini, gagal validasi pada form Tambah Data akan membuat
        // semua modal edit menampilkan nilai form Tambah Data.
        $isEditingThis = $errors->any()
            && old('__editing_id') == $it->id;

        // Nilai yang ditampilkan: hasil input terakhir bila baris inilah yang
        // gagal validasi, selain itu ambil apa adanya dari database.
        $editValue = fn (string $field) => $isEditingThis
            ? old($field, $it->$field)
            : $it->$field;
    @endphp

    <div
        class="modal fade"
        id="edit-master-{{ $it->id }}"
        tabindex="-1"
        aria-labelledby="edit-master-title-{{ $it->id }}"
        aria-hidden="true"
    >

        <div
            class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"
        >

            <div class="modal-content">


                <form
                    method="POST"
                    action="{{ route('admin.master.update', [$resource, $it->id]) }}"
                >

                    @csrf
                    @method('PUT')

                    {{-- Penanda baris yang sedang diedit, dipakai untuk
                         mengembalikan input dan membuka ulang modal ini
                         setelah gagal validasi. --}}
                    <input
                        type="hidden"
                        name="__editing_id"
                        value="{{ $it->id }}"
                    >


                    {{-- HEADER --}}
                    <div class="modal-header">

                        <div>

                            <h2
                                class="modal-title h5 fw-bold mb-1"
                                id="edit-master-title-{{ $it->id }}"
                            >
                                Edit {{ $labels[$resource] ?? $resource }}
                            </h2>

                            <p class="text-muted small mb-0">
                                Perbarui data kemudian tekan Simpan Perubahan.
                            </p>

                        </div>


                        <button
                            type="button"
                            class="btn-close"
                            data-bs-dismiss="modal"
                            aria-label="Tutup"
                        ></button>

                    </div>


                    {{-- BODY --}}
                    <div class="modal-body">

                        {{-- Alert error milik layout berada di belakang
                             backdrop modal, jadi ulang pesannya di sini. --}}
                        @if($isEditingThis)

                            <div class="alert alert-danger" role="alert">

                                <strong>Periksa input berikut:</strong>

                                <ul class="mb-0 mt-2">

                                    @foreach($errors->all() as $e)

                                        <li>{{ $e }}</li>

                                    @endforeach

                                </ul>

                            </div>

                        @endif

                        <div class="row g-3">


                            @foreach($fields as $f)


                                {{-- STATUS --}}
                                @if($f === 'is_active')

                                    <div class="col-12">

                                        <div class="form-check form-switch">

                                            <input
                                                id="edit_{{ $it->id }}_is_active"
                                                class="form-check-input"
                                                type="checkbox"
                                                name="is_active"
                                                value="1"
                                                @checked($isEditingThis ? old('is_active') : $it->is_active)
                                            >

                                            <label
                                                class="form-check-label"
                                                for="edit_{{ $it->id }}_is_active"
                                            >
                                                Data aktif
                                            </label>

                                        </div>

                                    </div>


                                {{-- CLASS ID --}}
                                @elseif($f === 'class_id')

                                    <div class="col-md-6">

                                        <label
                                            class="form-label"
                                            for="edit_{{ $it->id }}_class_id"
                                        >
                                            Kelas
                                        </label>

                                        <select
                                            id="edit_{{ $it->id }}_class_id"
                                            name="class_id"
                                            class="form-select"
                                        >

                                            <option value="">
                                                Tidak terkait kelas
                                            </option>


                                            @foreach($classes as $c)

                                                <option
                                                    value="{{ $c->id }}"
                                                    @selected(
                                                        (string) $editValue('class_id')
                                                        ===
                                                        (string) $c->id
                                                    )
                                                >
                                                    {{ $c->class_name }}
                                                </option>

                                            @endforeach

                                        </select>

                                    </div>


                                {{-- POINT REDUCTION --}}
                                @elseif($f === 'point_reduction')

                                    <div class="col-md-6">

                                        <label
                                            class="form-label required"
                                            for="edit_{{ $it->id }}_point_reduction"
                                        >
                                            Pengurangan Poin
                                        </label>

                                        <input
                                            id="edit_{{ $it->id }}_point_reduction"
                                            type="number"
                                            name="point_reduction"
                                            class="form-control"
                                            value="{{ $editValue('point_reduction') }}"
                                            required
                                            min="1"
                                            max="100"
                                        >

                                    </div>


                                {{-- DESCRIPTION --}}
                                @elseif($f === 'description')

                                    <div class="col-12">

                                        <label
                                            class="form-label"
                                            for="edit_{{ $it->id }}_description"
                                        >
                                            Deskripsi
                                        </label>

                                        <textarea
                                            id="edit_{{ $it->id }}_description"
                                            name="description"
                                            class="form-control"
                                            maxlength="1000"
                                            rows="4"
                                            placeholder="Opsional"
                                        >{{ $editValue('description') }}</textarea>

                                    </div>


                                {{-- NORMAL INPUT --}}
                                @else

                                    @php
                                        $isRequired = in_array(
                                            $f,
                                            $required,
                                            true
                                        );
                                    @endphp


                                    <div class="col-md-6">

                                        <label
                                            class="form-label {{ $isRequired ? 'required' : '' }}"
                                            for="edit_{{ $it->id }}_{{ $f }}"
                                        >
                                            {{ $labelFor($f) }}
                                        </label>

                                        <input
                                            id="edit_{{ $it->id }}_{{ $f }}"
                                            name="{{ $f }}"
                                            type="text"
                                            class="form-control"
                                            value="{{ $editValue($f) }}"
                                            maxlength="{{ $inputMax($f) }}"
                                            @required($isRequired)
                                        >

                                    </div>

                                @endif

                            @endforeach

                        </div>

                    </div>


                    {{-- FOOTER --}}
                    <div class="modal-footer">

                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-bs-dismiss="modal"
                        >
                            Batal
                        </button>


                        <button
                            type="submit"
                            class="btn btn-laporin"
                        >
                            Simpan Perubahan
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>


    {{-- Gagal validasi PUT hanya mengembalikan halaman index, modalnya
         tertutup. Buka ulang modal baris ini supaya admin melihat pesan
         error dan input yang sudah diisi tidak terasa hilang. --}}
    @if($isEditingThis)

        @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const el = document.getElementById('edit-master-{{ $it->id }}');

            if (el && window.bootstrap?.Modal) {
                bootstrap.Modal.getOrCreateInstance(el).show();
            }
        });
        </script>
        @endpush

    @endif

@endforeach

@endsection