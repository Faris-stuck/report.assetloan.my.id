@extends('layouts.app')
@section('title','Pengguna')
@section('content')
@php
    // Satu request hanya punya satu bag old()/$errors. Modal edit sudah memakai
    // penanda edit_user_id untuk memilih nilainya (lihat x-data di bawah), tapi
    // form "Tambah pengguna" belum: gagal validasi saat MENGUBAH pengguna
    // membuat form tambah ikut terisi data orang lain dan bertanda merah,
    // seolah-olah operator sedang menambah akun yang salah.
    $isEditing = (bool) old('edit_user_id');
    $isCreating = ! $isEditing;
@endphp
<div class="page-header">
    <div>
        <span class="page-kicker">SuperAdmin</span>
        <h1 class="page-title h2 mt-2">Manajemen Pengguna</h1>
        <p class="page-subtitle">Tambah akun pengelola dengan empat peran resmi, status aktif, dan kata sandi kuat.</p>
    </div>
</div>

<div class="laporin-card mb-4">
    <h2 class="h5 fw-bold mb-3">Tambah pengguna tervalidasi</h2>
    <form id="create-user-form" method="POST" action="{{ route('admin.users.store') }}" class="row g-3 align-items-end">
        @csrf
        <div class="col-md-6 col-lg-3">
            <label class="form-label required" for="name">Nama</label>
            <input id="name" name="name" value="{{ $isCreating ? old('name') : '' }}" class="form-control @if($isCreating)@error('name') is-invalid @enderror @endif" placeholder="Nama lengkap" required maxlength="150">
            @if($isCreating)@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
        </div>
        <div class="col-md-6 col-lg-3">
            <label class="form-label required" for="email">Surel</label>
            <input id="email" name="email" value="{{ $isCreating ? old('email') : '' }}" type="email" class="form-control @if($isCreating)@error('email') is-invalid @enderror @endif" placeholder="Contoh: nama@sekolah.sch.id" required maxlength="150" autocomplete="email">
            @if($isCreating)@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label required" for="password">Kata Sandi</label>
            <input id="password" name="password" type="password" class="form-control @if($isCreating)@error('password') is-invalid @enderror @endif" placeholder="Min 8 huruf+angka" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" title="Minimal 8 karakter dan harus memuat huruf serta angka." autocomplete="new-password">
            @if($isCreating)@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label required" for="role">Peran</label>
            <select id="role" name="role" class="form-select @if($isCreating)@error('role') is-invalid @enderror @endif" required>
                @foreach($roles as $role)
                    <option value="{{ $role }}" @selected($isCreating && old('role') === $role)>{{ str_replace('_',' ', $role) }}</option>
                @endforeach
            </select>
            @if($isCreating)@error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
        </div>
        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="phone">HP</label>
            <input id="phone" name="phone" value="{{ $isCreating ? old('phone') : '' }}" class="form-control @if($isCreating)@error('phone') is-invalid @enderror @endif" placeholder="Opsional" maxlength="30" pattern="[0-9+() .\-]+" title="Hanya angka dan karakter + ( ) spasi titik atau tanda hubung." inputmode="tel">
            @if($isCreating)@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
        </div>
        <div class="col-md-6 col-lg-1">
            <div class="form-check mt-3 pt-1">
                {{-- Checkbox tak tercentang tidak ikut terkirim, jadi tanpa hidden 0
                     old('is_active') selalu kosong dan default akan mencentang ulang
                     kotak yang sengaja dimatikan operator. storeUser() memakai
                     $request->boolean(), yang mengambil nilai terakhir. --}}
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" @checked($isCreating ? old('is_active', '1') : '1')>
                <label for="is_active" class="form-check-label">Aktif</label>
            </div>
        </div>
        <div class="col-md-6 col-lg-2">
            <button type="submit" class="btn btn-laporin w-100">Tambah</button>
        </div>
    </form>
</div>

<!-- Search & Filter Card -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('admin.users.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Cari nama atau email..." value="{{ request('search') }}" maxlength="100">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="filter_role">Peran</label>
            <select id="filter_role" name="role" class="form-select">
                <option value="">Semua</option>
                @foreach($roles as $role)
                    <option value="{{ $role }}" @selected(request('role') === $role)>{{ str_replace('_', ' ', $role) }}</option>
                @endforeach
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
            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<div x-data="{
        baseUrl: '{{ url('/admin/users') }}',
        editingUserId: @js(old('edit_user_id') ? (int) old('edit_user_id') : null),
        name: @json(old('edit_user_id') ? old('name') : ''),
        email: @json(old('edit_user_id') ? old('email') : ''),
        password: '',
        role: @json(old('edit_user_id') ? old('role') : \App\Models\User::ROLES[0]),
        phone: @json(old('edit_user_id') ? old('phone') : ''),
        is_active: @js(old('edit_user_id') ? (bool) old('is_active') : true),
        openEdit(user) {
            this.editingUserId = user.id;
            this.name = user.name;
            this.email = user.email;
            this.password = '';
            this.role = user.role;
            this.phone = user.phone ?? '';
            this.is_active = !!user.is_active;
            $dispatch('open-modal', 'edit-user');
        }
    }">
    <div class="laporin-card">
        <!-- Results Info -->
        @if(request('search') || request('role') || request('status'))
            <div class="mb-3 pb-3 border-bottom">
                <p class="text-muted small mb-0">
                    Menampilkan {{ $users->count() }} dari {{ $users->total() }} hasil
                    @if(request('search'))
                        untuk pencarian "<strong>{{ request('search') }}</strong>"
                    @endif
                </p>
            </div>
        @endif

        <!-- DESKTOP: Table View -->
        <div class="table-responsive d-none d-md-block">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Peran</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $u)
                        <tr>
                            <td><strong>{{ $u->name }}</strong></td>
                            <td>{{ $u->email }}</td>
                            <td>{{ str_replace('_',' ', $u->role) }}</td>
                            <td><span class="badge {{ $u->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $u->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                            <td class="d-flex gap-2">
                                @include('admin.users.partials.row-actions', [
                                    'u' => $u,
                                    'activeSuperadminCount' => $activeSuperadminCount,
                                    'stretch' => false,
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Belum ada data</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- MOBILE: Card View -->
        <div class="d-md-none">
            @forelse($users as $u)
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title">{{ $u->name }}</h6>
                        <p class="card-text text-muted small mb-2">{{ $u->email }}</p>
                        <div class="d-flex gap-2 justify-content-between align-items-center mb-3">
                            <span class="badge {{ $u->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                {{ $u->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                            <span class="text-muted small">{{ str_replace('_', ' ', $u->role) }}</span>
                        </div>
                        <div class="d-flex gap-2">
                            @include('admin.users.partials.row-actions', [
                                'u' => $u,
                                'activeSuperadminCount' => $activeSuperadminCount,
                                'stretch' => true,
                            ])
                        </div>
                    </div>
                </div>
            @empty
                <div class="alert alert-info">Belum ada pengguna</div>
            @endforelse
        </div>

        <!-- Pagination with preserved filters -->
        <div class="mt-3">{{ $users->appends(request()->query())->links() }}</div>
    </div>

    {{-- Prop 'focusable' tidak dideklarasikan components/modal.blade.php dan
         komponennya tidak merender $attributes, jadi atribut itu tidak pernah
         berefek — jebakan tab sudah selalu aktif di dalam komponen. --}}
    <x-modal name="edit-user" label="Ubah pengguna" :show="$isEditing">
        <form id="edit-user-form" method="POST" x-bind:action="baseUrl + '/' + editingUserId" class="p-3 p-lg-5">
            @csrf
            @method('PUT')
            <input type="hidden" name="edit_user_id" x-bind:value="editingUserId">

            <div class="mb-3">
                <h2 class="h5">Ubah pengguna</h2>
                <p class="mb-0 text-muted">Perbarui data pengguna atau nonaktifkan akun jika perlu.</p>
            </div>

            @if($isEditing && $errors->any())
                <div class="alert alert-danger mb-3">Periksa kembali field yang wajib diisi.</div>
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label required" for="edit_name">Nama</label>
                    <input id="edit_name" name="name" x-model="name" class="form-control @if($isEditing)@error('name') is-invalid @enderror @endif" placeholder="Nama lengkap" required maxlength="150">
                    @if($isEditing)@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                </div>

                <div class="col-md-6">
                    <label class="form-label required" for="edit_email">Surel</label>
                    <input id="edit_email" name="email" x-model="email" type="email" class="form-control @if($isEditing)@error('email') is-invalid @enderror @endif" placeholder="Contoh: nama@sekolah.sch.id" required maxlength="150" autocomplete="email">
                    @if($isEditing)@error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="edit_password">Kata Sandi</label>
                    <input id="edit_password" name="password" x-model="password" type="password" class="form-control @if($isEditing)@error('password') is-invalid @enderror @endif" placeholder="Biarkan kosong untuk simpan" minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" title="Minimal 8 karakter dan harus memuat huruf serta angka. Kosongkan bila tidak ingin mengganti." autocomplete="new-password">
                    @if($isEditing)@error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                </div>

                <div class="col-md-6">
                    <label class="form-label required" for="edit_role">Peran</label>
                    <select id="edit_role" name="role" x-model="role" class="form-select @if($isEditing)@error('role') is-invalid @enderror @endif" required>
                        @foreach($roles as $role)
                            <option value="{{ $role }}">{{ str_replace('_',' ', $role) }}</option>
                        @endforeach
                    </select>
                    @if($isEditing)@error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="edit_phone">HP</label>
                    <input id="edit_phone" name="phone" x-model="phone" class="form-control @if($isEditing)@error('phone') is-invalid @enderror @endif" placeholder="Opsional" maxlength="30" pattern="[0-9+() .\-]+" title="Hanya angka dan karakter + ( ) spasi titik atau tanda hubung." inputmode="tel">
                    @if($isEditing)@error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror @endif
                </div>

                <div class="col-md-6 d-flex align-items-center">
                    <div class="form-check mt-4 pt-1">
                        {{-- Sama seperti form tambah: hidden 0 memastikan status
                             nonaktif benar-benar terkirim, bukan hilang. --}}
                        <input type="hidden" name="is_active" value="0">
                        <input id="edit_is_active" class="form-check-input" type="checkbox" name="is_active" value="1" x-model="is_active">
                        <label for="edit_is_active" class="form-check-label">Aktif</label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" x-on:click="$dispatch('close-modal', 'edit-user')">Batal</button>
                    <button type="submit" class="btn btn-laporin">Simpan</button>
                </div>
            </div>
        </form>
    </x-modal>
</div>
@endsection

@if($errors->any())
@push('scripts')
<script>
    // Skrip di layouts/app.blade.php menempelkan is-invalid plus satu
    // .server-validation-feedback ke [name="..."] PERTAMA di dokumen. Halaman ini
    // punya DUA form dengan nama field yang sama (tambah pengguna dan modal ubah
    // pengguna), dan form tambah selalu lebih dulu di DOM. Akibatnya:
    //   - gagal validasi saat MENGUBAH pengguna menandai merah form TAMBAH, jadi
    //     operator diarahkan memperbaiki form yang salah;
    //   - pada form yang benar pesannya tampil dua kali, karena blok error
    //     server-side di atas sudah merender pesan yang sama.
    // Field yang ditandai layout selalu punya aria-invalid="true", sedangkan yang
    // dirender server-side tidak — itu yang dipakai untuk membedakannya.
    //
    // Catatan: jangan menulis nama direktif Blade berawalan @ di komentar JS ini.
    // Blade mengompilasi direktif di seluruh berkas, termasuk di dalam tag script.
    document.addEventListener('DOMContentLoaded', () => {
        const scope = document.getElementById(@js($isEditing ? 'edit-user-form' : 'create-user-form'));

        document.querySelectorAll('.server-validation-feedback').forEach((el) => el.remove());

        document.querySelectorAll('[aria-invalid="true"]').forEach((el) => {
            if (! scope || ! scope.contains(el)) {
                el.classList.remove('is-invalid');
                el.removeAttribute('aria-invalid');
            }
        });
    });
</script>
@endpush
@endif
