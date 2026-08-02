@extends('layouts.app')
@section('title','Master Data')
@section('content')
@php
    $labels = ['classes'=>'Kelas','subjects'=>'Mata Pelajaran','staff-units'=>'Unit Staf','locations'=>'Lokasi','violation-types'=>'Jenis Pelanggaran','damage-categories'=>'Kategori Kerusakan'];
    $required = [
        'classes' => ['class_name','grade_level','academic_year'],
        'subjects' => ['subject_name'],
        'staff-units' => ['unit_name'],
        'locations' => ['location_name'],
        'violation-types' => ['violation_name','point_reduction'],
        'damage-categories' => ['category_name'],
    ][$resource] ?? [];
    $inputMax = fn (string $field): int => in_array($field, ['class_name','grade_level','academic_year','room_name','location_type'], true) ? 80 : 150;
@endphp
<div class="page-header">
    <div>
        <span class="page-kicker">Master Data</span>
        <h1 class="page-title h2 mt-2">{{ $labels[$resource] ?? $resource }}</h1>
        <p class="page-subtitle">Kelola data referensi yang dipakai form laporan, QR, dan proses petugas.</p>
    </div>
</div>

<div class="laporin-card mb-4">
    <h2 class="h5 fw-bold mb-3">Tambah data tervalidasi</h2>
    <form method="POST" action="{{ route('admin.master.store',$resource) }}" class="row g-3 align-items-end">
        @csrf
        @foreach($fields as $f)
            @if($f === 'is_active')
                <div class="col-md-2"><div class="form-check"><input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" checked><label for="is_active" class="form-check-label">Aktif</label></div></div>
            @elseif($f === 'class_id')
                <div class="col-md-4"><label class="form-label" for="class_id">Kelas</label><select id="class_id" name="class_id" class="form-select"><option value="">Tidak terkait kelas</option>@foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->class_name }}</option>@endforeach</select></div>
            @elseif($f === 'point_reduction')
                <div class="col-md-3"><label class="form-label required" for="point_reduction">Pengurangan Poin</label><input id="point_reduction" type="number" name="point_reduction" class="form-control" required min="1" max="100" value="{{ old('point_reduction') }}"></div>
            @elseif($f === 'description')
                <div class="col-md-5"><label class="form-label" for="description">Deskripsi</label><input id="description" name="description" class="form-control" maxlength="1000" value="{{ old('description') }}" placeholder="Opsional"></div>
            @else
                @php($isRequired = in_array($f, $required, true))
                <div class="col-md-4"><label class="form-label {{ $isRequired ? 'required' : '' }}" for="{{ $f }}">{{ str_replace('_',' ', $f) }}</label><input id="{{ $f }}" name="{{ $f }}" class="form-control" placeholder="{{ str_replace('_',' ', $f) }}" value="{{ old($f) }}" maxlength="{{ $inputMax($f) }}" @required($isRequired)></div>
            @endif
        @endforeach
        <div class="col-md-2"><button class="btn btn-laporin w-100">Tambah</button></div>
    </form>
    <div class="helper-text">Validasi backend tetap menjadi sumber kebenaran; form ini membantu mencegah input salah sejak awal.</div>
</div>

@foreach($items as $it)
    <form id="master-update-{{ $resource }}-{{ $it->id }}" method="POST" action="{{ route('admin.master.update', [$resource, $it->id]) }}">
        @csrf
        @method('PUT')
    </form>
    <form id="master-delete-{{ $resource }}-{{ $it->id }}" method="POST" action="{{ route('admin.master.destroy', [$resource, $it->id]) }}" onsubmit="return confirm('Hapus/nonaktifkan data ini?')">
        @csrf
        @method('DELETE')
    </form>
@endforeach

<div class="laporin-card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
            <tr>
                @foreach($fields as $f)<th>{{ str_replace('_',' ',$f) }}</th>@endforeach
                <th class="text-end">Aksi</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $it)
                @php($updateForm = 'master-update-'.$resource.'-'.$it->id)
                @php($deleteForm = 'master-delete-'.$resource.'-'.$it->id)
                <tr>
                    @foreach($fields as $f)
                        <td style="min-width: 160px">
                            @if($f === 'is_active')
                                <div class="form-check"><input form="{{ $updateForm }}" class="form-check-input" type="checkbox" name="is_active" value="1" @checked((bool) $it->$f)><label class="form-check-label">Aktif</label></div>
                            @elseif($f === 'class_id')
                                <select form="{{ $updateForm }}" name="class_id" class="form-select form-select-sm"><option value="">Tidak terkait kelas</option>@foreach($classes as $c)<option value="{{ $c->id }}" @selected((int) $it->$f === (int) $c->id)>{{ $c->class_name }}</option>@endforeach</select>
                            @elseif($f === 'point_reduction')
                                <input form="{{ $updateForm }}" type="number" name="point_reduction" class="form-control form-control-sm" required min="1" max="100" value="{{ $it->$f }}">
                            @elseif($f === 'description')
                                <input form="{{ $updateForm }}" name="description" class="form-control form-control-sm" maxlength="1000" value="{{ $it->$f }}" placeholder="Opsional">
                            @else
                                @php($isRequired = in_array($f, $required, true))
                                <input form="{{ $updateForm }}" name="{{ $f }}" class="form-control form-control-sm" value="{{ $it->$f }}" maxlength="{{ $inputMax($f) }}" @required($isRequired)>
                            @endif
                        </td>
                    @endforeach
                    <td class="text-end text-nowrap">
                        <button form="{{ $updateForm }}" class="btn btn-sm btn-outline-laporin">Update</button>
                        <button form="{{ $deleteForm }}" class="btn btn-sm btn-outline-danger">Hapus</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($fields) + 1 }}" class="text-center text-muted py-4">Belum ada data.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $items->links() }}</div>
</div>
@endsection
