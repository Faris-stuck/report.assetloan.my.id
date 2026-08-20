@extends('layouts.app')
@section('title', $page['title'])
@section('meta_title', $page['meta_title'])
@section('meta_description', $page['description'])
@section('canonical'){{ $page['url'] }}@endsection
@section('content')
<nav aria-label="Breadcrumb" class="mb-3">
    <ol class="breadcrumb small mb-0">
        <li class="breadcrumb-item"><a href="{{ route('public.report') }}">Beranda LAPORIN</a></li>
        <li class="breadcrumb-item active" aria-current="page">{{ $page['heading'] }}</li>
    </ol>
</nav>
<div class="hero-card p-4 p-lg-5 mb-4">
    <span class="page-kicker">Panduan LAPORIN • diperbarui {{ $page['updated'] }}</span>
    <h1 class="page-title display-6 mt-3">{{ $page['heading'] }}</h1>
    <p class="page-subtitle fs-6">{{ $page['intro'] }}</p>
    <div class="d-flex flex-wrap gap-2 mt-4">
        <a class="btn btn-laporin" href="{{ route('public.report') }}#form-laporan">Buat Laporan</a>
        <a class="btn btn-outline-laporin" href="{{ route('track.form') }}">Lacak Laporan</a>
    </div>
</div>
<div class="row g-4">
    <div class="col-lg-8">
        <article class="laporin-card p-4 p-lg-5 seo-prose">
            @foreach($page['sections'] as $section)
                <h2>{{ $section['h2'] }}</h2>
                @foreach($section['paragraphs'] as $paragraph)<p>{!! $paragraph !!}</p>@endforeach
                @if(!empty($section['items']))<ul>@foreach($section['items'] as $item)<li>{!! $item !!}</li>@endforeach</ul>@endif
            @endforeach
            <div class="alert alert-light border rounded-4"><strong>Privasi:</strong> Jangan mengunggah atau menulis data pribadi yang tidak diperlukan. Simpan nomor laporan dan kode akses, dan jangan membagikan kode akses kepada pihak yang tidak berkepentingan.</div>
        </article>
    </div>
    <div class="col-lg-4">
        <aside class="laporin-card p-4 sticky-lg-top seo-aside">
            <h2 class="h5 fw-bold">Halaman terkait</h2>
            <div class="d-grid gap-2">
                @foreach($page['related'] as $link)
                    <a class="btn btn-outline-laporin" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                @endforeach
            </div>
        </aside>
    </div>
</div>
@endsection
@push('head')
<script type="application/ld+json">{!! json_encode($page['jsonld'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush
