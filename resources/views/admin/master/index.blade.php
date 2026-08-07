@extends('layouts.app')
@section('title','Master Data')
@section('content')
@php
    $labels = ['classes'=>'Kelas','subjects'=>'Mata Pelajaran','staff-units'=>'Unit Staf','locations'=>'Lokasi','violation-types'=>'Jenis Pelanggaran','damage-categories'=>'Kategori Kerusakan'];
    $fieldLabels = [
        'class_name' => 'Nama Kelas',
        'grade_level' => 'Tingkat',
        'academic_year' => 'Tahun Ajaran',
        'room_name' => 'Nama Ruangan',
        'location_type' => 'Jenis Lokasi',
        'subject_name' => 'Nama Mapel',
        'unit_name' => 'Nama Unit',
        'violation_name' => 'Jenis Pelanggaran',
        'category_name' => 'Nama Kategori',
        'description' => 'Deskripsi',
        'is_active' => 'Aktif',
        'class_id' => 'Kelas',
        'point_reduction' => 'Pengurangan Poin',
    ];
    $required = [
        'classes' => ['class_name','grade_level','academic_year'],
        'subjects' => ['subject_name'],
        'staff-units' => ['unit_name'],
        'locations' => ['location_name'],
        'violation-types' => ['violation_name','point_reduction'],
        'damage-categories' => ['category_name'],
    ][$resource] ?? [];
    $inputMax = fn (string $field): int => in_array($field, ['class_name','grade_level','academic_year','room_name','location_type'], true) ? 80 : 150;
    $labelFor = fn (string $field): string => $fieldLabels[$field] ?? str_replace('_',' ', $field);
@endphp
<div class="page-header">
    <div>
        <span class="page-kicker">Master Data</span>
        <h1 class="page-title h2 mt-2">{{ $labels[$resource] ?? $resource }}</h1>
        <p class="page-subtitle">Kelola data referensi yang dipakai form laporan, QR, dan proses petugas.</p>
    </div>
</div>

<!-- Create Card -->
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
                <div class="col-md-4"><label class="form-label {{ $isRequired ? 'required' : '' }}" for="{{ $f }}">{{ $labelFor($f) }}</label><input id="{{ $f }}" name="{{ $f }}" class="form-control" placeholder="{{ $labelFor($f) }}" value="{{ old($f) }}" maxlength="{{ $inputMax($f) }}" @required($isRequired)></div>
            @endif
        @endforeach
        <div class="col-md-2"><button class="btn btn-laporin w-100">Tambah</button></div>
    </form>
    <small class="text-muted">Validasi backend tetap menjadi sumber kebenaran; form ini membantu mencegah input salah sejak awal.</small>
</div>

<!-- Search & Filter Card -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('admin.master', $resource) }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-5">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Cari nama atau email..." value="{{ request('search') }}" maxlength="100">
        </div>

        <div class="col-md-6 col-lg-3">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                <option value="active" @selected(request('status') === 'active')>Aktif</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
            </select>
        </div>

        <div class="col-md-6 col-lg-4 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
            <a href="{{ route('admin.master', $resource) }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Modal Edit Forms (hidden, used via Alpine) -->
@foreach($items as $it)
    <form id="master-update-{{ $resource }}-{{ $it->id }}" method="POST" action="{{ route('admin.master.update', [$resource, $it->id]) }}" style="display:none;">
        @csrf
        @method('PUT')
    </form>
    <form id="master-delete-{{ $resource }}-{{ $it->id }}" method="POST" action="{{ route('admin.master.destroy', [$resource, $it->id]) }}" style="display:none;">
        @csrf
        @method('DELETE')
    </form>
@endforeach

<!-- Main Table with Alpine.js for Modal -->
<div x-data="{
    baseUrl: '{{ route('admin.master', $resource) }}',
    editingId: @js(old('edit_id') ? (int) old('edit_id') : null),
    editData: @js(old('edit_id') ? old() : {}),
    
    openEdit(item) {
        this.editingId = item.id;
        this.editData = item;
        $dispatch('open-modal', 'edit-master');
    }
}">
    <div class="laporin-card">
        <!-- Results Info -->
        @if(request('search') || request('status'))
            <div class="mb-3 pb-3 border-bottom">
                <p class="text-muted small mb-0">
                    Menampilkan {{ $items->count() }} dari {{ $items->total() }} hasil
                    @if(request('search'))
                        untuk pencarian "<strong>{{ request('search') }}</strong>"
                    @endif
                    @if(request('status'))
                        dengan status <strong>{{ request('status') === 'active' ? 'Aktif' : 'Nonaktif' }}</strong>
                    @endif
                </p>
            </div>
        @endif

        <!-- Table -->
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                <tr>
                    @foreach($fields as $f)<th>{{ $labelFor($f) }}</th>@endforeach
                    <th class="text-end">Aksi</th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $it)
                    <tr>
                        @foreach($fields as $f)
                            <td>
                                @if($f === 'is_active')
                                    <span class="badge {{ $it->$f ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $it->$f ? 'Aktif' : 'Nonaktif' }}</span>
                                @elseif($f === 'class_id')
                                    {{ $it->class ? $it->class->class_name : 'Tidak terkait' }}
                                @elseif($f === 'description')
                                    <span class="text-muted small">{{ $it->$f ? substr($it->$f, 0, 50) . '...' : '-' }}</span>
                                @else
                                    {{ $it->$f }}
                                @endif
                            </td>
                        @endforeach
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-laporin"
                                x-on:click="openEdit(@js($it))">
                                Edit
                            </button>
                            <form method="POST" action="{{ route('admin.master.destroy', [$resource, $it->id]) }}" 
                                  style="display:inline" onsubmit="return confirm('Hapus data ini?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($fields) + 1 }}" class="text-center text-muted py-4">Belum ada data.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination with preserved filters -->
        <div class="mt-3">
            {{ $items->appends(request()->query())->links() }}
        </div>
    </div>

    <!-- Modal Edit Form -->
    <x-modal name="edit-master" :show="old('edit_id') ? true : false" focusable>
        <form method="POST" x-bind:action="editingId ? '{{ url('/admin/master') }}/' + editingId : '#'" class="p-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_id" x-bind:value="editingId">

            <div class="mb-4">
                <h2 class="h5 fw-bold mb-1">Ubah {{ strtolower($labels[$resource] ?? $resource) }}</h2>
                <p class="text-muted small mb-0">Perbarui data sesuai kebutuhan</p>
            </div>

            @if(old('edit_id') && $errors->any())
                <div class="alert alert-danger mb-3" role="alert">
                    <strong>Error:</strong> Periksa kembali field yang wajib diisi.
                </div>
            @endif

            <div class="row g-3 mb-3">
                @foreach($fields as $f)
                    @if($f === 'is_active')
                        <div class="col-12">
                            <div class="form-check">
                                <input id="edit_is_active" class="form-check-input" type="checkbox" 
                                       name="is_active" value="1" 
                                       x-bind:checked="editData.is_active">
                                <label for="edit_is_active" class="form-check-label">Aktif</label>
                            </div>
                        </div>
                    @elseif($f === 'class_id')
                        <div class="col-12">
                            <label class="form-label" for="edit_class_id">Kelas</label>
                            <select id="edit_class_id" name="class_id" class="form-select" x-bind:value="editData.class_id">
                                <option value="">Tidak terkait kelas</option>
                                @foreach($classes as $c)
                                    <option value="{{ $c->id }}">{{ $c->class_name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif($f === 'point_reduction')
                        <div class="col-12">
                            <label class="form-label required" for="edit_point_reduction">Pengurangan Poin</label>
                            <input id="edit_point_reduction" type="number" name="point_reduction" class="form-control"
                                   x-bind:value="editData.point_reduction" required min="1" max="100">
                        </div>
                    @elseif($f === 'description')
                        <div class="col-12">
                            <label class="form-label" for="edit_description">Deskripsi</label>
                            <input id="edit_description" name="description" type="text" class="form-control"
                                   x-bind:value="editData.description ?? ''" maxlength="1000" placeholder="Opsional">
                        </div>
                    @else
                        @php($isRequired = in_array($f, $required, true))
                        <div class="col-12">
                            <label class="form-label {{ $isRequired ? 'required' : '' }}" for="edit_{{ $f }}">{{ $labelFor($f) }}</label>
                            <input id="edit_{{ $f }}" name="{{ $f }}" type="text" class="form-control"
                                   x-bind:value="editData.{{ $f }} ?? ''" maxlength="{{ $inputMax($f) }}" 
                                   @required($isRequired)>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                <button type="button" class="btn btn-outline-secondary"
                        x-on:click="$dispatch('close-modal', 'edit-master')">
                    Batal
                </button>
                <button type="submit" class="btn btn-laporin">
                    Simpan
                </button>
            </div>
        </form>
    </x-modal>
</div>

@endsection
