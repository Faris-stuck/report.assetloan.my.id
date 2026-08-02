@extends('layouts.app')
@section('title','Users')
@section('content')
<div class="page-header"><div><span class="page-kicker">SuperAdmin</span><h1 class="page-title h2 mt-2">Manajemen User</h1><p class="page-subtitle">Tambah akun pengelola dengan empat role resmi, status aktif, dan password kuat.</p></div></div>
<div class="laporin-card mb-4">
    <h2 class="h5 fw-bold mb-3">Tambah user tervalidasi</h2>
    <form method="POST" action="{{ route('admin.users.store') }}" class="row g-3 align-items-end">
        @csrf
        <div class="col-md-6 col-lg-3"><label class="form-label required" for="name">Nama</label><input id="name" name="name" value="{{ old('name') }}" class="form-control" placeholder="Nama lengkap" required maxlength="150"></div>
        <div class="col-md-6 col-lg-3"><label class="form-label required" for="email">Email</label><input id="email" name="email" value="{{ old('email') }}" type="email" class="form-control" placeholder="Email" required maxlength="150" autocomplete="email"></div>
        <div class="col-md-6 col-lg-2"><label class="form-label required" for="password">Password</label><input id="password" name="password" type="password" class="form-control" placeholder="Min 8 huruf+angka" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*\d).{8,}" autocomplete="new-password"></div>
        <div class="col-md-6 col-lg-2"><label class="form-label required" for="role">Role</label><select id="role" name="role" class="form-select" required>@foreach(\App\Models\User::ROLES as $role)<option value="{{ $role }}" @selected(old('role') === $role)>{{ str_replace('_',' ', $role) }}</option>@endforeach</select></div>
        <div class="col-md-6 col-lg-2"><label class="form-label" for="phone">HP</label><input id="phone" name="phone" value="{{ old('phone') }}" class="form-control" placeholder="Opsional" maxlength="30" pattern="[0-9+() .-]+" inputmode="tel"></div>
        <div class="col-md-6 col-lg-2"><div class="form-check"><input id="is_active" class="form-check-input" type="checkbox" name="is_active" value="1" checked><label for="is_active" class="form-check-label">Aktif</label></div></div>
        <div class="col-md-6 col-lg-2"><button class="btn btn-laporin w-100">Tambah</button></div>
    </form>
</div>
<div class="laporin-card"><div class="table-responsive"><table class="table"><thead><tr><th>Nama</th><th>Email</th><th>Role</th><th>Status</th></tr></thead><tbody>@foreach($users as $u)<tr><td><strong>{{ $u->name }}</strong></td><td>{{ $u->email }}</td><td>{{ str_replace('_',' ', $u->role) }}</td><td><span class="badge {{ $u->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $u->is_active?'Aktif':'Nonaktif' }}</span></td></tr>@endforeach</tbody></table></div><div class="mt-3">{{ $users->links() }}</div></div>
@endsection
