@extends('layouts.app')
@section('title','Buat Laporan - LAPORIN')
@section('meta_title','LAPORIN SMK Taruna Bangsa Bekasi | Lapor Perundungan')
@section('meta_description','LAPORIN SMK Taruna Bangsa Bekasi untuk melaporkan perundungan, pelanggaran siswa, dan kerusakan fasilitas. Buat laporan tanpa login dan lacak status.')
{{-- Halaman wizard (/lapor, /lapor/{qr}, /lapor/langkah/{step}) mengirim
     noindex, jadi tidak boleh mengarahkan canonical ke '/'. Pasangan
     noindex + canonical lintas-URL membuat Google berisiko meneruskan
     noindex ke target canonical, yaitu homepage. Self-canonical. --}}
@section('canonical'){{ request()->routeIs('public.report') ? url('/') : url()->current() }}@endsection
@section('robots', request()->routeIs('public.report') ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' : 'noindex, follow, noarchive')
@section('content')
@php
    $today = date('Y-m-d');
    $errorKeys = $errors->getBag('default')->keys();
    // Step 4 fields (Konfirmasi & Kirim)
    $step4Fields = ['attachments','attachments.0','attachments.1','attachments.2','consent','captcha','form','report_number'];
    // Step 3 fields (Detail) — ringkasan pelanggaran
    $step3Fields = ['title','urgency','related_class_id','location_id','custom_location','incident_date','incident_time','description','reporter_position','bullying_type','victim_name','victim_class_id','alleged_actor_name','alleged_actor_class_id','witness_name','impact_description','item_name','item_category','damage_condition','suspected_cause','priority'];
    // Step 2 fields (Jenis)
    $step2Fields = ['report_type'];
    // Determine which step to start on based on errors
    $initialStep = (int) ($wizardStep ?? 1);
    $wizardStep = $initialStep;

    $reportType = old('report_type', $wizardStep > 1 ? (session('report_submit_forms.'.session('report_submit_token').'.wizard_data.report_type') ?? 'violation') : 'violation');

    // Session bukan mekanisme pembatas jumlah laporan. Batas submit diterapkan
    // oleh middleware throttle berdasarkan IP + device (5 laporan / 120 menit).
    $reportSubmitToken = $reportSubmitToken ?? session('report_submit_token');
@endphp

{{-- Satu-satunya <h1> halaman ini sebelumnya berada di dalam <details>
     yang tertutup di bawah form, sehingga outline heading bagian yang
     terlihat langsung dimulai dari <h2>. Judul utama harus ada di awal
     konten, bukan di dalam disclosure. --}}
<header class="mb-3">
    <h1 class="h4 fw-bold mb-1">Lapor Perundungan, Pelanggaran Siswa, dan Kerusakan Fasilitas</h1>
    <p class="small-muted mb-0">Kanal laporan resmi SMK Taruna Bangsa Bekasi. Tanpa login, dan status laporan dapat dilacak dengan nomor laporan serta kode akses.</p>
</header>

{{-- ============================================================ --}}
{{-- FORM UTAMA --}}
{{-- ============================================================ --}}
<form id="form-laporan" method="POST" action="{{ route('public.report.step.store', $wizardStep) }}" enctype="multipart/form-data">
@csrf
<input type="hidden" name="report_submit_token" value="{{ $reportSubmitToken ?? session('report_submit_token') }}">

{{-- Step tracker --}}
<div class="laporin-card mb-3 step-track">
    <div class="row g-2 text-center">
        @foreach([1=>'Identitas',2=>'Jenis',3=>'Detail',4=>'Kirim'] as $n=>$label)
            <div class="col">
                <div class="step-dot-wrapper">
                    <button type="button" class="step-dot{{ $n <= $wizardStep ? ' active' : '' }}" data-step-dot="{{ $n }}" style="min-width:44px;min-height:44px;" aria-label="Langkah {{ $n }}" title="Langkah {{ $n }}">{{ $n }}</button>
                </div>
                <div class="small mt-2 fw-semibold" style="max-width:100px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin:0 auto;">{{ $label }}</div>
            </div>
        @endforeach
    </div>
    <p class="mt-3 mb-0 text-center small" style="font-size:14px;" data-step-hint>Isi lengkap, lalu lanjut ke tahap berikutnya.</p>
</div>

@if($errors->any())
<div class="alert alert-danger mt-3 mb-3" id="step-error-alert" role="alert">
    <div class="d-flex align-items-start">
        <i class="fas fa-exclamation-circle me-2 mt-1 flex-shrink-0"></i>
        <div class="flex-grow-1">
            <strong class="d-block mb-1">Lengkapi formulir dengan benar</strong>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endif

<div class="laporin-card wizard-panel p-3 p-md-4 p-lg-5">

{{-- ============================================================ --}}
{{-- LANGKAH 1: IDENTITAS                                          --}}
{{-- ============================================================ --}}
@if($wizardStep === 1)
<section data-step="1" class="wizard-step is-active">
    <span class="page-kicker">Langkah 1</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Identitas Pelapor</h2>
    <p class="small-muted mb-4">Isi yang paling penting saja.</p>
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label required" for="reporter_name">Nama Pelapor</label>
            <input id="reporter_name" name="reporter_name" value="{{ old('reporter_name') }}" class="form-control required" required maxlength="150" autocomplete="name" placeholder="Nama lengkap">
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label required" for="reporter_class_id">Kelas</label>
            <select id="reporter_class_id" name="reporter_class_id" class="form-select required" required>
                <option value="">Pilih kelas</option>
                @include('public.partials.class-options', ['selectedClassId' => old('reporter_class_id')])
            </select>
            <small class="text-muted">Dikelompokkan per jurusan dan diurutkan.</small>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="reporter_absence_number">No. Absen</label>
            <input id="reporter_absence_number" type="number" name="reporter_absence_number" value="{{ old('reporter_absence_number') }}" min="1" max="60" class="form-control" placeholder="1–60">
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label required" for="reporter_phone">No. HP</label>
            <input id="reporter_phone" name="reporter_phone" value="{{ old('reporter_phone') }}" class="form-control required" required maxlength="30" pattern="[0-9+() .\-]+" inputmode="tel" autocomplete="tel" aria-describedby="reporter_phone_help" placeholder="Contoh: 0812 3456 7890">
            <small id="reporter_phone_help" class="text-muted">Nomor HP wajib diisi. Gunakan 8-15 digit.</small>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="reporter_email">Email</label>
            <input id="reporter_email" type="email" name="reporter_email" value="{{ old('reporter_email') }}" class="form-control" maxlength="150" autocomplete="email" placeholder="Contoh: nama@email.com" aria-describedby="reporter_email_help">
            <small id="reporter_email_help" class="text-muted">Opsional. Jika diisi, email digunakan untuk notifikasi status laporan.</small>
        </div>
    </div>
</section>
@endif

{{-- ============================================================ --}}
{{-- LANGKAH 2: JENIS LAPORAN                                      --}}
{{-- ============================================================ --}}
@if($wizardStep === 2)
<section data-step="2" class="wizard-step laporin-card p-3 p-md-4 p-lg-5{{ $initialStep === 2 ? ' is-active' : '' }}">
    <span class="page-kicker">Langkah 2</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Pilih Jenis Laporan <span class="required-mark" aria-hidden="true">*</span></h2>
    <p class="small-muted mb-4">Pilih satu jenis laporan yang paling sesuai.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="choice-card p-4 w-100{{ $reportType === 'violation' ? ' is-selected' : '' }}" data-report-type="violation">
                <input type="radio" name="report_type" value="violation" required @checked($reportType === 'violation')>
                <strong class="d-block mt-2">Perundungan / Pelanggaran</strong>
                <span class="small-muted">Untuk perundungan, pembullyan, atau pelanggaran tata tertib. Ditangani oleh Kesiswaan.</span>
            </label>
        </div>
        <div class="col-md-6">
            <label class="choice-card p-4 w-100{{ $reportType === 'damage' ? ' is-selected' : '' }}" data-report-type="damage">
                <input type="radio" name="report_type" value="damage" required @checked($reportType === 'damage')>
                <strong class="d-block mt-2">Kerusakan Fasilitas</strong>
                <span class="small-muted">Untuk kerusakan meja, proyektor, AC, toilet, pintu, dll. Ditangani oleh Sarpras.</span>
            </label>
        </div>
    </div>
</section>
@endif

{{-- ============================================================ --}}
{{-- LANGKAH 3: DETAIL (RINGKAS UNTUK PELANGGARAN)                --}}
{{-- ============================================================ --}}
@if($wizardStep === 3)
<section data-step="3" class="wizard-step laporin-card p-3 p-md-4 p-lg-5{{ $initialStep === 3 ? ' is-active' : '' }}">
    <span class="page-kicker">Langkah 3</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Detail Kejadian</h2>
    <p class="small-muted mb-4">Isi singkat dan jelas.</p>
    <div class="row g-3">

        {{-- FIELD UNIVERSAL: Urgensi --}}
        <div class="col-12 col-md-6">
            <label class="form-label required" for="urgency">Tingkat Urgensi</label>
            <select id="urgency" name="urgency" class="form-select required" required>
                @foreach(['rendah','sedang','tinggi','darurat'] as $urgency)
                    <option value="{{ $urgency }}" @selected(old('urgency','sedang') === $urgency)>
                        {{ match($urgency) { 'rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi', 'darurat' => 'Darurat' } }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- FIELD UNIVERSAL: Tanggal kejadian (wajib di server) --}}
        <div class="col-12 col-md-6">
            <label class="form-label required" for="incident_date">Tanggal kejadian</label>
            <input id="incident_date" type="date" name="incident_date" value="{{ old('incident_date') }}" max="{{ $today }}" class="form-control required" required>
            <small class="text-muted">Tanggal saat kejadian berlangsung (tidak boleh di masa depan).</small>
        </div>

        {{-- Judul tetap digunakan untuk laporan pelanggaran; laporan kerusakan
             membentuk judul secara otomatis dari nama barang/fasilitas. --}}
        <div class="col-12{{ $reportType === 'violation' ? '' : ' d-none' }}" data-report-type-content="violation">
            <fieldset class="border-0 p-0 m-0" {{ $reportType === 'violation' ? '' : 'disabled' }}>
            <label class="form-label required" for="title">Judul singkat</label>
            <input id="title" name="title" value="{{ old('title') }}" class="form-control required" required maxlength="200"
                placeholder="Contoh: Perundungan di Lab Komputer">
            </fieldset>
        </div>

        {{-- ======================================================== --}}
        {{-- VIOLATION: 4 FIELD RINGKAS                                --}}
        {{-- ======================================================== --}}
        <div class="col-12{{ $reportType === 'violation' ? '' : ' d-none' }}" data-report-type-content="violation">
            <fieldset class="border-0 p-0 m-0" {{ $reportType === 'violation' ? '' : 'disabled' }}>
            <div class="detail-box">
                <h3 class="h6 fw-bold mb-3">Detail pelanggaran</h3>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="related_class_id">Kelas pelaku</label>
                        <select id="related_class_id" name="related_class_id" class="form-select" required>
                            <option value="">Pilih kelas</option>
                            @include('public.partials.class-options', ['selectedClassId' => old('related_class_id')])
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="alleged_actor_name">Nama terduga pelaku</label>
                        <input id="alleged_actor_name" name="alleged_actor_name" value="{{ old('alleged_actor_name') }}" class="form-control required" required maxlength="150" placeholder="Nama lengkap pelaku">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="alleged_actor_class_id">Kelas terduga pelaku</label>
                        <select id="alleged_actor_class_id" name="alleged_actor_class_id" class="form-select">
                            <option value="">Pilih kelas (opsional)</option>
                            @include('public.partials.class-options', ['selectedClassId' => old('alleged_actor_class_id')])
                        </select>
                        <small class="text-muted">Opsional jika pelaku berasal dari kelas yang sama diketahui.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="description">Kronologi singkat</label>
                        <textarea id="description" name="description" class="form-control required" rows="4" required maxlength="5000"
                            placeholder="Jelaskan kejadian singkatnya.">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>
            </fieldset>
        </div>

        {{-- ======================================================== --}}
        {{-- DAMAGE: FIELD LENGKAP                                    --}}
        {{-- ======================================================== --}}
        <div class="col-12{{ $reportType === 'damage' ? '' : ' d-none' }}" data-report-type-content="damage">
            <fieldset class="border-0 p-0 m-0" {{ $reportType === 'damage' ? '' : 'disabled' }}>
            <div class="detail-box">
                <h3 class="h6 fw-bold mb-3">Detail kerusakan</h3>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="item_name">Nama barang / fasilitas</label>
                        <input id="item_name" name="item_name" value="{{ old('item_name') }}" class="form-control required" placeholder="Contoh: Proyektor, AC, Pintu" maxlength="150" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="description_damage">Deskripsi kerusakan / dampak</label>
                        <textarea id="description_damage" name="description" class="form-control required" rows="4" required maxlength="5000" placeholder="Jelaskan kerusakan dan dampaknya secara singkat.">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>
            </fieldset>
        </div>

    </div>
</section>
@endif

{{-- ============================================================ --}}
{{-- LANGKAH 4: KONFIRMASI & KIRIM                                  --}}
{{-- ============================================================ --}}
@if($wizardStep === 4)
<section data-step="4" class="wizard-step laporin-card p-3 p-md-4 p-lg-5{{ $initialStep === 4 ? ' is-active' : '' }}">
    <span class="page-kicker">Langkah 4</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Konfirmasi & Kirim</h2>
    <p class="small-muted mb-4">Cek ulang, lalu kirim.</p>

    {{-- Lampiran dipindah ke step akhir --}}
    <div class="row g-3">
        <div class="col-12">
            <div class="detail-box">
                <h3 class="h6 fw-bold mb-3">Bukti Foto / Dokumen (Opsional)</h3>
                <label class="form-label" for="attachments">Unggah Bukti</label>
                <input id="attachments" type="file" name="attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf">
                <small class="text-muted">Maksimal 3 file; JPG, PNG, WEBP, atau PDF; maksimal 4MB per file.</small>
            </div>
        </div>

        <div class="col-12">
            <div class="detail-box">
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" required @checked(old('consent') === '1')>
                    <label class="form-check-label required" for="consent">
                        Saya menyatakan laporan adalah benar
                    </label>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label required" for="captcha">CAPTCHA: berapa {{ $captchaQuestion }}?</label>
            <input id="captcha" name="captcha" value="{{ old('captcha') }}" class="form-control required" required inputmode="numeric" pattern="[0-9]+" maxlength="2" placeholder="Jawaban angka">
        </div>
    </div>
</section>
@endif

</div>{{-- end .wizard-panel --}}

<div class="bottom-action mt-4" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
    <div class="row g-2 align-items-center w-100">
        <div class="col-12 col-sm">
            <span class="small-muted text-center">
                @if($wizardStep === 4)
                    Periksa kembali seluruh isian, lalu kirim laporan.
                @else
                    Isi lengkap, lalu lanjut ke langkah berikutnya.
                @endif
            </span>
        </div>
        <div class="col-12 col-sm-auto">
            <div class="d-flex gap-2 flex-wrap">
                @if($wizardStep > 1)
                    <a href="{{ route('public.report.step', $wizardStep - 1) }}" class="btn btn-outline-laporin flex-fill flex-sm-grow-0" style="min-height:44px;">Kembali</a>
                @endif
                @if($wizardStep < 4)
                    <button type="submit" class="btn btn-laporin flex-fill flex-sm-grow-0" style="min-height:44px;">Lanjut</button>
                @else
                    <button type="submit" class="btn btn-laporin flex-fill flex-sm-grow-0" style="min-height:44px;">Kirim Laporan</button>
                @endif
            </div>
        </div>
    </div>
</div>
</form>

<details id="alur-validasi" class="laporin-card mt-4 seo-disclosure">
    <summary class="fw-semibold">Tentang LAPORIN dan panduan pelaporan</summary>
    <div class="pt-3 seo-prose">
        <h2>LAPORIN SMK Taruna Bangsa Bekasi — Lapor Perundungan, Pelanggaran Siswa, dan Kerusakan Fasilitas</h2>
        <p>LAPORIN membantu warga SMK Taruna Bangsa Bekasi membuat laporan perundungan, pembullyan, pelanggaran siswa, dan kerusakan fasilitas secara terstruktur, aman, dan dapat dilacak menggunakan nomor laporan serta kode akses.</p>
        <h3>Layanan LAPORIN untuk warga SMK Taruna Bangsa Bekasi</h3>
        <p>LAPORIN adalah kanal pelaporan publik sekolah untuk mencatat kejadian yang memerlukan tindak lanjut. Topik utama yang dapat dilaporkan adalah perundungan atau pembullyan, pelanggaran siswa, dan kerusakan fasilitas sekolah. Setelah laporan masuk, pelapor dapat memantau status melalui halaman Lacak Laporan.</p>
        <div class="row g-3">
            <div class="col-md-4"><a href="{{ route('seo.bullying-guide') }}"><strong>Lapor pembullyan &amp; perundungan</strong></a><br><span class="small-muted">Panduan kronologi, bukti, dan alur tindak lanjut.</span></div>
            <div class="col-md-4"><a href="{{ route('seo.student-violation') }}"><strong>Lapor pelanggaran siswa</strong></a><br><span class="small-muted">Untuk kedisiplinan, tata tertib, dan kejadian tidak aman.</span></div>
            <div class="col-md-4"><a href="{{ route('seo.facility-damage') }}"><strong>Lapor kerusakan fasilitas</strong></a><br><span class="small-muted">Untuk fasilitas kelas, laboratorium, toilet, listrik, dan sarana sekolah.</span></div>
        </div>
    </div>
</details>
@endsection
@push('head')
@php
    $homeJsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => ['WebApplication', 'Service'],
                '@id' => url('/').'#laporin-service',
                'name' => 'LAPORIN',
                'applicationCategory' => 'ReportingApplication',
                'operatingSystem' => 'Web',
                'url' => url('/'),
                'areaServed' => 'SMK Taruna Bangsa Bekasi',
                'serviceType' => 'Pelaporan perundungan, pembullyan, pelanggaran siswa, dan kerusakan fasilitas sekolah',
                'provider' => ['@id' => url('/').'#organization'],
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($homeJsonLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@push('scripts')
<script src="{{ asset('js/laporin-report-fix.js') }}?v={{ @filemtime(public_path('js/laporin-report-fix.js')) ?: time() }}" defer></script>
@endpush
