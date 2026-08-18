<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $siteName = 'LAPORIN SMK Taruna Bangsa Bekasi';
        $pageTitle = trim($__env->yieldContent('title', 'LAPORIN'));
        $metaTitle = trim($__env->yieldContent('meta_title', ''));
        if ($metaTitle === '') $metaTitle = str_contains($pageTitle, 'LAPORIN') ? $pageTitle : $pageTitle.' | LAPORIN';
        $metaDescription = trim($__env->yieldContent('meta_description', 'LAPORIN adalah kanal laporan perundungan, pembullyan, pelanggaran siswa, dan kerusakan fasilitas untuk warga SMK Taruna Bangsa Bekasi.'));
        $metaTitle = html_entity_decode($metaTitle, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $metaDescription = html_entity_decode($metaDescription, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $canonicalUrl = trim($__env->yieldContent('canonical', url()->current()));
        $robotsMeta = trim($__env->yieldContent('robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'));
        $ogType = trim($__env->yieldContent('og_type', 'website'));
        $ogImage = trim($__env->yieldContent('og_image', asset('images/laporin-social-card.png')));
        $ogImageAlt = trim($__env->yieldContent('og_image_alt', 'LAPORIN — kanal laporan perundungan dan kerusakan SMK Taruna Bangsa Bekasi'));
    @endphp
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <meta name="robots" content="{{ $robotsMeta }}">
    <meta name="author" content="LAPORIN SMK Taruna Bangsa Bekasi">
    <meta name="theme-color" content="#00a651">
    <link rel="canonical" href="{{ $canonicalUrl }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:secure_url" content="{{ $ogImage }}">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $ogImageAlt }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $metaTitle }}">
    <meta name="twitter:description" content="{{ $metaDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <meta name="twitter:image:alt" content="{{ $ogImageAlt }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/laporin.css') }}?v={{ filemtime(public_path('css/laporin.css')) }}" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    @stack('head')
</head>
<body>
<a href="#main-content" class="skip-link">Lewati ke konten utama</a>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top" aria-label="Navigasi utama LAPORIN">
    <div class="container mobile-shell">
        <a class="navbar-brand" href="{{ route('public.report') }}" aria-label="Beranda LAPORIN">
            <img class="brand-mark brand-logo" src="{{ asset('images/branding/logo tb.png') }}" alt="Logo SMK Taruna Bangsa" width="38" height="38">
            <span>LAPORIN</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Buka menu navigasi utama">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div id="mainNav" class="collapse navbar-collapse">
            @php($currentUser = auth()->user())
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-1">
                @guest
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('public.report') ? 'active' : '' }}" href="{{ route('public.report') }}">Buat Laporan</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('seo.bullying-guide') ? 'active' : '' }}" href="{{ route('seo.bullying-guide') }}">Panduan Lapor</a></li>
                    <li class="nav-item"><a class="nav-link" href="{{ route('public.report') }}#alur-validasi">Alur Validasi</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('track.*') ? 'active' : '' }}" href="{{ route('track.form') }}">Lacak</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('seo.faq') ? 'active' : '' }}" href="{{ route('seo.faq') }}">Pertanyaan Umum</a></li>
                @else
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Dasbor</a></li>
                    @if($currentUser->canAccessMenuFor('kesiswaan'))<li class="nav-item"><a class="nav-link {{ request()->routeIs('kesiswaan.*') ? 'active' : '' }}" href="{{ route('kesiswaan.index') }}">Kesiswaan</a></li>@endif
                    @if($currentUser->canAccessMenuFor('sarpras'))<li class="nav-item"><a class="nav-link {{ request()->routeIs('sarpras.*') ? 'active' : '' }}" href="{{ route('sarpras.index') }}">Sarpras</a></li>@endif
                    @if($currentUser->isSuperadmin())
                        <li class="nav-item dropdown"><a class="nav-link dropdown-toggle {{ request()->is('admin*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-label="Panel Admin menu" aria-expanded="false">Panel Admin</a><ul class="dropdown-menu shadow border-0 rounded-4 p-2" role="menu"><li role="none"><a class="dropdown-item rounded-3" href="{{ route('admin.users.index') }}" role="menuitem">Pengguna</a></li><li role="none"><a class="dropdown-item rounded-3" href="{{ route('admin.qrcodes.index') }}" role="menuitem">Kode QR</a></li><li role="none"><a class="dropdown-item rounded-3" href="{{ route('admin.audit') }}" role="menuitem">Catatan Audit</a></li></ul></li>
                    @endif
                @endguest
            </ul>
            <div class="d-flex flex-column flex-lg-row gap-2 align-items-lg-center ms-lg-3">
                @auth
                    <div class="nav-user-chip text-truncate small" title="{{ $currentUser->name }} · {{ $currentUser->role }}">{{ $currentUser->name }} · {{ str_replace('_',' ', $currentUser->role) }}</div>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-sm btn-light w-100 w-lg-auto">Keluar</button></form>
                @else
                @endauth
            </div>
        </div>
    </div>
</nav>
<main id="main-content" class="main-shell"><div class="container mobile-shell">
    @if(session('status'))<div class="alert alert-success shadow-sm" role="status" aria-live="polite">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger shadow-sm" role="alert" aria-live="assertive"><strong>Periksa input berikut:</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div><script id="validation-errors-json" type="application/json">@json($errors->getBag('default')->messages())</script>@endif
    @yield('content')
</div></main>
@include('components.ai-chat')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{const source=document.getElementById('validation-errors-json');const nav=document.getElementById('mainNav');const navDropdowns=nav?.querySelectorAll('.dropdown-toggle');if(nav){nav.querySelectorAll('a').forEach(link=>link.addEventListener('click',()=>{if(window.bootstrap?.Collapse){const collapse=bootstrap.Collapse.getOrCreateInstance(nav,{toggle:false});if(nav.classList.contains('show'))collapse.hide();}}));}if(navDropdowns){navDropdowns.forEach(toggle=>{toggle.addEventListener('show.bs.dropdown',()=>toggle.setAttribute('aria-expanded','true'));toggle.addEventListener('hide.bs.dropdown',()=>toggle.setAttribute('aria-expanded','false'));});}if(!source||!window.CSS||!CSS.escape)return;let errors={};try{errors=JSON.parse(source.textContent||'{}');}catch(_){return;}let firstInvalid=null;Object.entries(errors).forEach(([name,messages])=>{const names=[name];if(/\.\d+$/.test(name))names.push(name.replace(/\.\d+$/,'[]'));if(name.includes('.'))names.push(`${name.split('.')[0]}[]`);const field=[...new Set(names)].map(candidate=>document.querySelector(`[name="${CSS.escape(candidate)}"]`)).find(Boolean);if(!field)return;field.classList.add('is-invalid');field.setAttribute('aria-invalid','true');if(!firstInvalid&&field.offsetParent!==null)firstInvalid=field;const feedback=document.createElement('div');feedback.className='invalid-feedback d-block server-validation-feedback';feedback.textContent=Array.isArray(messages)?messages[0]:String(messages);const target=field.closest('.form-check')||field;target.insertAdjacentElement('afterend',feedback);});if(firstInvalid){firstInvalid.scrollIntoView({behavior:'smooth',block:'center'});firstInvalid.focus({preventScroll:true});}});
</script>
@stack('scripts')
</body>
</html>