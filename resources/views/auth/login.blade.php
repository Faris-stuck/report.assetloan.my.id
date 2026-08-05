@extends('layouts.app')
@section('title','Masuk - LAPORIN')
@section('meta_title','Masuk Pengelola LAPORIN')
@section('meta_description','Halaman masuk terbatas untuk pengelola dan siswa yang memiliki akun LAPORIN.')
@section('robots','noindex, nofollow, noarchive')
@section('content')
<div class="login-hero">
    <div class="row justify-content-center align-items-center g-4">
        <div class="col-lg-5">
            <span class="page-kicker">Area terbatas</span>
            <h1 class="page-title h2 mt-2">Masuk ke LAPORIN</h1>
            <p class="page-subtitle">Pengelola memakai email. Siswa dapat memakai NIS. Tidak ada registrasi publik agar akses tetap terkontrol oleh sekolah.</p>
            <div class="laporin-card card-soft mt-4">
                <div class="d-flex gap-3">
                    <span class="menu-icon">🔒</span>
                    <div><strong>Akses berbasis role</strong><div class="small-muted">Menu dashboard otomatis mengikuti hak akses akun.</div></div>
                </div>
            </div>
        </div>
        <div class="col-md-7 col-lg-5">
            <div class="laporin-card p-4 p-lg-5">
                <h2 class="h4 fw-bold mb-1">Masuk LAPORIN</h2>
                <p class="small-muted mb-4">Masukkan surel/NIS dan kata sandi/PIN yang diberikan admin.</p>
                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label required" for="login">Surel / NIS</label>
                        <input id="login" name="login" value="{{ old('login') }}" class="form-control" required autofocus autocomplete="username" placeholder="surel@sekolah.sch.id atau NIS">
                    </div>
                    <div class="mb-3">
                        <label class="form-label required" for="password">Kata Sandi / PIN</label>
                        <input id="password" type="password" name="password" class="form-control" required autocomplete="current-password" placeholder="Masukkan kata sandi/PIN">
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" name="remember" id="remember">
                        <label class="form-check-label" for="remember">Ingat saya di perangkat ini</label>
                    </div>
                    <button class="btn btn-laporin w-100">Masuk</button>
                </form>
                <div class="helper-text text-center mt-3">Lupa akses? Hubungi SuperAdmin sekolah.</div>
            </div>
        </div>
    </div>
</div>
@endsection
