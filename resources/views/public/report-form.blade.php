@extends('layouts.app')
@section('title','Buat Laporan - LAPORIN')
@section('meta_title','LAPORIN SMK Taruna Bangsa Bekasi | Lapor Perundungan')
@section('meta_description','Buat laporan perundungan, pembullyan, pelanggaran siswa, atau kerusakan fasilitas SMK Taruna Bangsa Bekasi secara aman dan terlacak.')
@section('canonical'){{ url('/') }}@endsection
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
    $initialStep = count(array_intersect($errorKeys, $step4Fields)) ? 4
        : (count(array_intersect($errorKeys, $step3Fields)) ? 3
        : (count(array_intersect($errorKeys, $step2Fields)) ? 2 : 1));

    // Nilai awal untuk kondisi field (dipakai untuk render server juga).
    $reporter = old('reporter_type', 'siswa');
    $reportType = old('report_type', 'violation');

    // Hanya blokir form bila ada flag submit yang eksplisit.
    // Jangan menonaktifkan form hanya karena token sesi kosong atau sesi tidak tersedia.
    $reportSubmitToken = $reportSubmitToken ?? session('report_submit_token');
    $formBlocked = (bool) session('report_form_submitted', false);
    $formDisabled = $formBlocked;
@endphp

{{-- Anti-duplikat hanya aktif ketika ada flag yang jelas dari submit sukses --}}
@if($formDisabled)
<div class="hero-card p-4 p-lg-5 mb-4 text-center">
    <div class="hero-content">
        <span class="page-kicker">Laporan Sudah Terkirim</span>
        <h1 class="page-title h3 mt-3">Anda sudah mengirimkan laporan</h1>
        <p class="page-subtitle mx-auto mt-2">Setiap pelapor hanya dapat mengirim satu laporan dalam satu sesi. Untuk membuat laporan baru, buka halaman ini di tab baru.</p>
        <div class="d-flex flex-wrap justify-content-center gap-2 mt-4">
            <a href="{{ route('track.form') }}" class="btn btn-laporin">Lacak Status Laporan</a>
            <a href="{{ url('/') }}" class="btn btn-outline-laporin">Kembali ke Beranda</a>
        </div>
    </div>
</div>
@else
{{-- ============================================================ --}}
{{-- HERO & ALUR                                                    --}}
{{-- ============================================================ --}}
<div class="hero-card p-4 p-lg-5 mb-4">
    <div class="hero-content row align-items-center g-4">
        <div class="col-lg-8">
            <span class="page-kicker">Kanal Laporan SMK Taruna Bangsa Bekasi</span>
            <h1 class="page-title display-6 mt-3">Laporkan dengan cepat dan jelas</h1>
            <p class="page-subtitle fs-6">Isi data yang penting, lalu kirim. Setelah selesai, simpan nomor laporan dan kode akses untuk melihat statusnya.</p>
        </div>
        <div class="col-lg-4">
            <div class="laporin-card bg-white h-100">
                <div class="d-flex gap-3 align-items-start mb-3"><span class="menu-icon">1</span><div><strong>Tanpa login</strong><div class="small-muted">Langsung isi dan kirim.</div></div></div>
                <div class="d-flex gap-3 align-items-start mb-3"><span class="menu-icon">2</span><div><strong>4 langkah</strong><div class="small-muted">Tidak perlu banyak kolom.</div></div></div>
                        <div class="d-flex gap-3 align-items-start"><span class="menu-icon">3</span><div><strong>Pelacakan mudah</strong><div class="small-muted">Cek status dengan nomor + kode.</div></div></div>
            </div>
        </div>
    </div>
</div>

<div class="laporin-card mb-4" id="alur-validasi">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Alur singkat</h2>
            <div class="small-muted">Isi sesuai urutan, lalu lanjut ke langkah berikutnya.</div>
        </div>
        <span class="badge text-bg-success rounded-pill">Aktif</span>
    </div>
    <div class="flowchart">
        <div class="flow-node">1. Identitas<small>Data pelapor</small></div>
        <div class="flow-node">2. Jenis<small>Perundungan / Kerusakan</small></div>
        <div class="flow-node">3. Detail<small>Judul, pelaku, kronologi</small></div>
        <div class="flow-node">4. Kirim<small>Bukti & konfirmasi</small></div>
    </div>
</div>

<div class="laporin-card mb-4 seo-prose">
    <div class="row g-4 align-items-start">
        <div class="col-lg-8">
            <h2 class="h4 fw-bold">Kanal laporan untuk sekolah</h2>
            <p class="mb-2">Gunakan LAPORIN untuk melaporkan perundungan, pelanggaran, atau kerusakan fasilitas yang perlu ditindaklanjuti.</p>
            <p class="small-muted mb-0">Baca panduan singkat di <a href="{{ route('seo.bullying-guide') }}">Panduan Lapor</a>.</p>
        </div>
        <div class="col-lg-4">
            <div class="d-grid gap-2">
                <a class="btn btn-outline-laporin" href="{{ route('seo.bullying-guide') }}">Panduan Lapor</a>
                <a class="btn btn-outline-laporin" href="{{ route('seo.faq') }}">Pertanyaan Umum</a>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- FORM UTAMA                                                     --}}
{{-- ============================================================ --}}
<form id="form-laporan" method="POST" action="{{ route('public.report.store') }}" enctype="multipart/form-data" x-data="reportWizard()" x-init="syncConditionalFields()" onsubmit="return window.LaporinWizard ? window.LaporinWizard.onSubmit(event) : true">
@csrf
<input type="hidden" name="report_submit_token" value="{{ $reportSubmitToken ?? session('report_submit_token') }}">
<input type="hidden" name="qr_code_id" value="{{ $qrCode?->id }}">

{{-- Wizard bertahap: hanya satu langkah tampil; "Lanjut" memvalidasi langkah
     berjalan lalu menampilkan langkah berikutnya, "Kembali" untuk mengulang. --}}

<div class="alert alert-danger mt-3 mb-3 d-none" id="step-error-alert" role="alert">
    <div class="d-flex align-items-start">
        <i class="fas fa-exclamation-circle me-2 mt-1 flex-shrink-0"></i>
        <div class="flex-grow-1">
            <strong class="d-block mb-1">Lengkapi formulir dengan benar</strong>
            <div class="small" data-step-error-text></div>
        </div>
    </div>
</div>

<div class="wizard-panel">

    {{-- Progress tahapan: hanya langkah aktif yang tampil; langkah berjalan dikendalikan
         JS inline murni (tanpa ketergantungan Alpine). --}}
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small fw-bold text-uppercase text-muted">Tahap <span data-step-indicator>1</span> dari 4</span>
        <span class="small fw-bold text-success" data-step-frac>1/4</span>
    </div>
    <div class="wizard-progress mb-3" role="progressbar" aria-label="Progress pengisian laporan" aria-valuemin="1" aria-valuemax="4">
        <div class="progress-bar" data-step-progress style="width: 25%"></div>
    </div>

{{-- ============================================================ --}}
{{-- LANGKAH 1: IDENTITAS                                          --}}
{{-- ============================================================ --}}
<section data-step="1" class="wizard-step{{ $initialStep === 1 ? ' is-active' : '' }}">
    <span class="page-kicker">Langkah 1</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Identitas Pelapor</h2>
    <p class="small-muted mb-4">Isi yang paling penting saja.</p>
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label class="form-label required" for="reporter_type">Jenis Pelapor</label>
            <select id="reporter_type" name="reporter_type" class="form-select required" x-model="formData.step1.reporter_type" @change="reporter=formData.step1.reporter_type; syncConditionalFields()" required onchange="if (window.LaporinWizard) { window.LaporinWizard.setReporter(this.value); }">
                <option value="siswa" @selected($reporter === 'siswa')>Siswa</option>
                <option value="guru" @selected($reporter === 'guru')>Guru</option>
                <option value="staff" @selected($reporter === 'staff')>Staf</option>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label required" for="reporter_name">Nama Pelapor</label>
            <input id="reporter_name" name="reporter_name" x-model="formData.step1.reporter_name" class="form-control required" required maxlength="150" autocomplete="name" placeholder="Nama lengkap">
        </div>

        {{-- Siswa --}}
        <div class="col-12 col-md-6 conditional-field{{ $reporter === 'siswa' ? '' : ' d-none' }}" data-reporter-role="siswa">
            <label class="form-label required" for="reporter_class_id">Kelas</label>
            <select id="reporter_class_id" name="reporter_class_id" class="form-select required" x-model="formData.step1.reporter_class_id" required :disabled="reporter!=='siswa'">
                <option value="">Pilih kelas</option>
                @include('public.partials.class-options', ['selectedClassId' => old('reporter_class_id')])
            </select>
            <small class="text-muted">Dikelompokkan per jurusan dan diurutkan.</small>
        </div>
        <div class="col-12 col-md-6 conditional-field{{ $reporter === 'siswa' ? '' : ' d-none' }}" data-reporter-role="siswa">
            <label class="form-label" for="reporter_absence_number">No. Absen</label>
            <input id="reporter_absence_number" type="number" name="reporter_absence_number" x-model="formData.step1.reporter_absence_number" min="1" max="60" class="form-control" :disabled="reporter!=='siswa'" placeholder="1–60">
        </div>

        {{-- Guru --}}
        <div class="col-12 col-md-6 conditional-field{{ $reporter === 'guru' ? '' : ' d-none' }}" data-reporter-role="guru">
            <label class="form-label required" for="reporter_subject_id">Mata Pelajaran</label>
            <select id="reporter_subject_id" name="reporter_subject_id" class="form-select required" x-model="formData.step1.reporter_subject_id" required :disabled="reporter!=='guru'">
                <option value="">Pilih mapel</option>
                @foreach($subjects as $s)<option value="{{ $s->id }}" @selected(old('reporter_subject_id') == $s->id)>{{ $s->subject_name }}</option>@endforeach
            </select>
        </div>

        {{-- Staf --}}
        <div class="col-12 col-md-6 conditional-field{{ $reporter === 'staff' ? '' : ' d-none' }}" data-reporter-role="staff">
            <label class="form-label required" for="reporter_staff_unit_id">Unit Staf</label>
            <select id="reporter_staff_unit_id" name="reporter_staff_unit_id" class="form-select required" x-model="formData.step1.reporter_staff_unit_id" required :disabled="reporter!=='staff'">
                <option value="">Pilih unit</option>
                @foreach($staffUnits as $u)<option value="{{ $u->id }}" @selected(old('reporter_staff_unit_id') == $u->id)>{{ $u->unit_name }}</option>@endforeach
            </select>
        </div>

        {{-- No. HP wajib, email opsional --}}
        <div class="col-12 col-md-6">
            <label class="form-label required" for="reporter_phone">No. HP</label>
            <input id="reporter_phone" name="reporter_phone" x-model="formData.step1.reporter_phone" class="form-control required" required maxlength="30" pattern="[0-9+() .\-]+" inputmode="tel" autocomplete="tel" aria-describedby="reporter_phone_help" placeholder="Contoh: 0812 3456 7890">
            <small id="reporter_phone_help" class="text-muted">Nomor HP wajib diisi. Gunakan 8-15 digit.</small>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="reporter_email">Surel</label>
            <input id="reporter_email" type="email" name="reporter_email" x-model="formData.step1.reporter_email" class="form-control" maxlength="150" autocomplete="email" placeholder="Contoh: nama@surel.com" aria-describedby="reporter_email_help">
            <small id="reporter_email_help" class="text-muted">Opsional. Jika diisi, surel digunakan untuk notifikasi status laporan.</small>
        </div>
    </div>
</section>

{{-- ============================================================ --}}
{{-- LANGKAH 2: JENIS LAPORAN                                      --}}
{{-- ============================================================ --}}
<section data-step="2" class="wizard-step laporin-card p-3 p-md-4 p-lg-5{{ $initialStep === 2 ? ' is-active' : '' }}">
    <span class="page-kicker">Langkah 2</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Pilih Jenis Laporan <span class="required-mark" aria-hidden="true">*</span></h2>
    <p class="small-muted mb-4">Pilih satu jenis laporan yang paling sesuai.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="choice-card p-4 w-100" :class="type === 'violation' ? 'is-selected' : ''" data-report-type="violation">
                <input type="radio" name="report_type" value="violation" x-model="formData.step2.report_type" @change="type=formData.step2.report_type; syncConditionalFields()" required @checked($reportType === 'violation') onchange="if (window.LaporinWizard) { window.LaporinWizard.setReportType(this.value); }">
                <strong class="d-block mt-2">Perundungan / Pelanggaran</strong>
                <span class="small-muted">Untuk perundungan, pembullyan, atau pelanggaran tata tertib. Ditangani oleh Kesiswaan.</span>
            </label>
        </div>
        <div class="col-md-6">
            <label class="choice-card p-4 w-100" :class="type === 'damage' ? 'is-selected' : ''" data-report-type="damage">
                <input type="radio" name="report_type" value="damage" x-model="formData.step2.report_type" @change="type=formData.step2.report_type; syncConditionalFields()" required @checked($reportType === 'damage') onchange="if (window.LaporinWizard) { window.LaporinWizard.setReportType(this.value); }">
                <strong class="d-block mt-2">Kerusakan Fasilitas</strong>
                <span class="small-muted">Untuk kerusakan meja, proyektor, AC, toilet, pintu, dll. Ditangani oleh Sarpras.</span>
            </label>
        </div>
    </div>
</section>

{{-- ============================================================ --}}
{{-- LANGKAH 3: DETAIL (RINGKAS UNTUK PELANGGARAN)                --}}
{{-- ============================================================ --}}
<section data-step="3" class="wizard-step laporin-card p-3 p-md-4 p-lg-5{{ $initialStep === 3 ? ' is-active' : '' }}">
    <span class="page-kicker">Langkah 3</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Detail Kejadian</h2>
    <p class="small-muted mb-4">Isi singkat dan jelas.</p>
    <div class="row g-3">

        {{-- FIELD UNIVERSAL: Judul --}}
        <div class="col-12">
            <label class="form-label required" for="title">Judul singkat</label>
            <input id="title" name="title" x-model="formData.step3.title" class="form-control required" required maxlength="200"
                :placeholder="type==='violation' ? 'Contoh: Perundungan di Lab Komputer' : 'Contoh: Lampu kelas X Mati'">
        </div>

        {{-- FIELD UNIVERSAL: Urgensi --}}
        <div class="col-12 col-md-6">
            <label class="form-label required" for="urgency">Tingkat Urgensi</label>
            <select id="urgency" name="urgency" class="form-select required" x-model="formData.step3.urgency" required>
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
            <input id="incident_date" type="date" name="incident_date" x-model="formData.step3.incident_date" max="{{ $today }}" class="form-control required" required>
            <small class="text-muted">Tanggal saat kejadian berlangsung (tidak boleh di masa depan).</small>
        </div>

        {{-- ======================================================== --}}
        {{-- VIOLATION: 4 FIELD RINGKAS                                --}}
        {{-- ======================================================== --}}
        <div class="col-12{{ $reportType === 'violation' ? '' : ' d-none' }}" data-report-type-content="violation">
            <div class="detail-box">
                <h3 class="h6 fw-bold mb-3">Detail pelanggaran</h3>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label required" for="related_class_id">Kelas pelaku</label>
                        <select id="related_class_id" name="related_class_id" class="form-select" required x-model="formData.step3.related_class_id">
                            <option value="">Pilih kelas</option>
                            @include('public.partials.class-options', ['selectedClassId' => old('related_class_id')])
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="alleged_actor_name">Nama terduga pelaku</label>
                        <input id="alleged_actor_name" name="alleged_actor_name" x-model="formData.step3.alleged_actor_name" class="form-control required" required maxlength="150" placeholder="Nama lengkap pelaku">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="alleged_actor_class_id">Kelas terduga pelaku</label>
                        <select id="alleged_actor_class_id" name="alleged_actor_class_id" class="form-select" x-model="formData.step3.alleged_actor_class_id">
                            <option value="">Pilih kelas (opsional)</option>
                            @include('public.partials.class-options', ['selectedClassId' => old('alleged_actor_class_id')])
                        </select>
                        <small class="text-muted">Opsional jika pelaku berasal dari kelas yang sama diketahui.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="description">Kronologi singkat</label>
                        <textarea id="description" name="description" class="form-control required" rows="4" required maxlength="5000"
                            placeholder="Jelaskan kejadian singkatnya."
                            x-model="formData.step3.description"></textarea>
                    </div>
                </div>
            </div>
        </div>

        {{-- ======================================================== --}}
        {{-- DAMAGE: FIELD LENGKAP                                    --}}
        {{-- ======================================================== --}}
        <div class="col-12{{ $reportType === 'damage' ? '' : ' d-none' }}" data-report-type-content="damage">
            <div class="detail-box">
                <h3 class="h6 fw-bold mb-3">Detail kerusakan</h3>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label required" for="item_name">Nama barang / fasilitas</label>
                        <input id="item_name" name="item_name" x-model="formData.step3.item_name" class="form-control required" placeholder="Contoh: Proyektor, AC, Pintu" maxlength="150" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="location_id_damage">Lokasi</label>
                        <select id="location_id_damage" name="location_id" class="form-select" x-model="formData.step3.location_id">
                            <option value="">Pilih lokasi</option>
                            @foreach($locations as $l)<option value="{{ $l->id }}" @selected(old('location_id') == $l->id)>{{ $l->location_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="damage_condition">Kondisi kerusakan</label>
                        <textarea id="damage_condition" name="damage_condition" class="form-control required" rows="4" placeholder="Jelaskan bagian yang rusak." maxlength="2000" x-model="formData.step3.damage_condition" required></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label required" for="description_damage">Deskripsi dampak</label>
                        <textarea id="description_damage" name="description" class="form-control required" rows="4" required maxlength="5000" placeholder="Sebutkan dampaknya secara singkat." x-model="formData.step3.description"></textarea>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

{{-- ============================================================ --}}
{{-- LANGKAH 4: KONFIRMASI & KIRIM                                  --}}
{{-- ============================================================ --}}
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
                <input id="attachments" type="file" name="attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" @change="validateAttachments($event)">
                <small class="text-muted">Maksimal 3 file; JPG, PNG, WEBP, atau PDF; maksimal 4MB per file.</small>
            </div>
        </div>

        <div class="col-12">
            <div class="detail-box">
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" name="consent" value="1" x-model="formData.step4.consent" id="consent" required>
                    <label class="form-check-label required" for="consent">
                        Saya menyatakan laporan adalah benar
                    </label>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label required" for="captcha">CAPTCHA: berapa {{ $captchaQuestion }}?</label>
            <input id="captcha" name="captcha" x-model="formData.step4.captcha" class="form-control required" required inputmode="numeric" pattern="[0-9]+" maxlength="2" placeholder="Jawaban angka">
        </div>
    </div>
</section>

</div>{{-- end .wizard-panel --}}

<div class="bottom-action mt-4" style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
    <div class="row g-2 align-items-center w-100">
        <div class="col-12 col-sm d-none d-sm-block">
            <span class="small-muted text-center" data-step-hint>Isi lengkap, lalu lanjut ke tahap berikutnya.</span>
        </div>
        <div class="col-12 col-sm-auto">
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-laporin flex-fill flex-sm-grow-0" data-wizard-action="prev" onclick="if (window.LaporinWizard) { window.LaporinWizard.prev(); return false; }" style="display:none; min-height: 44px; height: auto;" aria-label="Kembali ke langkah sebelumnya">Kembali</button>
                <button type="button" class="btn btn-laporin flex-fill flex-sm-grow-0" data-wizard-action="next" onclick="if (window.LaporinWizard) { window.LaporinWizard.next(); return false; }" style="min-height: 44px; height: auto;" aria-label="Lanjut ke langkah berikutnya">Lanjut</button>
                <button type="submit" class="btn btn-laporin flex-fill flex-sm-grow-0" data-wizard-action="submit" style="display:none; min-height: 44px; height: auto;" aria-label="Kirim laporan">Kirim Laporan</button>
            </div>
        </div>
    </div>
</div>
</form>
@endif{{-- end @if($formDisabled) --}}
@endsection

@push('head')
@php
    $homeJsonLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebSite',
                '@id' => url('/').'#website',
                'url' => url('/'),
                'name' => 'LAPORIN SMK Taruna Bangsa Bekasi',
                'description' => 'Kanal laporan perundungan, pembullyan, pelanggaran siswa, dan kerusakan fasilitas SMK Taruna Bangsa Bekasi.',
                'inLanguage' => 'id-ID',
            ],
            [
                '@type' => ['WebApplication', 'Service'],
                '@id' => url('/').'#laporin-service',
                'name' => 'LAPORIN',
                'applicationCategory' => 'ReportingApplication',
                'operatingSystem' => 'Web',
                'url' => url('/'),
                'areaServed' => 'SMK Taruna Bangsa Bekasi',
                'serviceType' => 'Pelaporan perundungan, pembullyan, pelanggaran siswa, dan kerusakan fasilitas sekolah',
            ],
            [
                '@type' => 'School',
                '@id' => url('/').'#school',
                'name' => 'SMK Taruna Bangsa Bekasi',
                'url' => url('/'),
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($homeJsonLd, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@push('scripts')
<script>
/* =========================================================
   WIZARD LANGKAH-DEMI-LANGKAH (JS inline murni)
   - Hanya langkah aktif yang tampil (class .is-active).
   - Tidak bergantung Alpine: tetap bekerja meskipun Alpine gagal dimuat.
   ========================================================= */
(function () {
    var current = 1;

    function getForm() { return document.getElementById('form-laporan'); }
    function getSection(n) { return document.querySelector('section[data-step="' + n + '"]'); }

    function getFieldLabel(input) {
        if (!input) return 'field wajib';
        var label = input.id ? document.querySelector('label[for="' + input.id + '"]') : null;
        var raw = (label && label.textContent) || input.getAttribute('aria-label') || input.name || 'field wajib';
        return raw.replace(/\s*\*\s*$/, '').trim();
    }

    function setStepError(msg) {
        var alert = document.getElementById('step-error-alert');
        if (!alert) return;
        var t = alert.querySelector('[data-step-error-text]');
        if (t) t.textContent = msg || '';
        if (msg) {
            alert.classList.remove('d-none');
            alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            alert.classList.add('d-none');
        }
    }

    function syncButtons() {
        var form = getForm();
        if (!form) return;
        [['prev', current > 1], ['next', current < 4], ['submit', current === 4]].forEach(function (pair) {
            var btn = form.querySelector('[data-wizard-action="' + pair[0] + '"]');
            if (btn) btn.style.display = pair[1] ? '' : 'none';
        });
    }

    function syncProgress() {
        var bar = document.querySelector('[data-step-progress]');
        if (bar) bar.style.width = ((current / 4) * 100) + '%';
        var ind = document.querySelector('[data-step-indicator]');
        if (ind) ind.textContent = current;
        var frac = document.querySelector('[data-step-frac]');
        if (frac) frac.textContent = current + '/4';
        var hint = document.querySelector('[data-step-hint]');
        if (hint) hint.textContent = current === 4 ? 'Periksa kembali seluruh isian, lalu kirim.' : 'Isi lengkap, lalu lanjut ke tahap berikutnya.';
    }

    function showStep(n) {
        current = Math.min(4, Math.max(1, Number(n) || 1));
        [1, 2, 3, 4].forEach(function (i) {
            var s = getSection(i);
            if (s) s.classList.toggle('is-active', i === current);
        });
        syncButtons();
        syncProgress();
    }

    function isVisible(el) {
        if (typeof el.checkVisibility === 'function') {
            return el.checkVisibility();
        }
        return el.offsetParent !== null;
    }

    function validateStep() {
        var s = getSection(current);
        if (!s) return true;
        var fields = s.querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
            var f = fields[i];
            if (f.disabled) continue;
            // Lewati field tersembunyi (mis. box tipe lain yang d-none) — tidak
            // relevan untuk langkah/jenis laporan saat ini.
            if (!isVisible(f)) continue;
            if (!f.checkValidity()) {
                f.reportValidity();
                setStepError('Lengkapi atau perbaiki ' + getFieldLabel(f) + '.');
                return false;
            }
        }
        setStepError('');
        return true;
    }

    window.LaporinWizard = {
        next: function () {
            if (validateStep()) showStep(current + 1);
        },
        prev: function () {
            setStepError('');
            showStep(current - 1);
        },
        onSubmit: function () {
            if (current === 4 && validateStep()) return true;
            var form = getForm();
            if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return false;
        },
        setReporter: function (val) {
            var groups = document.querySelectorAll('[data-reporter-role]');
            for (var i = 0; i < groups.length; i++) {
                var g = groups[i];
                var match = g.getAttribute('data-reporter-role') === val;
                g.classList.toggle('d-none', !match);
                var fields = g.querySelectorAll('input, select, textarea');
                for (var j = 0; j < fields.length; j++) {
                    fields[j].disabled = !match;
                }
            }
        },
        setReportType: function (val) {
            var v = document.querySelector('[data-report-type-content="violation"]');
            var d = document.querySelector('[data-report-type-content="damage"]');
            if (v) v.classList.toggle('d-none', val !== 'violation');
            if (d) d.classList.toggle('d-none', val !== 'damage');
        },
        init: function () {
            [1, 2, 3, 4].forEach(function (i) {
                var s = getSection(i);
                if (s && s.classList.contains('is-active')) current = i;
            });
            var repSel = document.getElementById('reporter_type');
            if (repSel) this.setReporter(repSel.value);
            var typeRad = document.querySelector('input[name="report_type"]:checked');
            if (typeRad) this.setReportType(typeRad.value);
            showStep(current);
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        if (window.LaporinWizard) window.LaporinWizard.init();
    });
})();
</script>
<script>
window.reportWizard = function () {
    return {
        type: @js(old('report_type','violation')),
        reporter: @js(old('reporter_type','siswa')),
        stepError: '',
        formData: {
            step1: {
                reporter_type: @js(old('reporter_type','siswa')),
                reporter_name: @js(old('reporter_name','')),
                reporter_class_id: @js(old('reporter_class_id','')),
                reporter_absence_number: @js(old('reporter_absence_number','')),
                reporter_subject_id: @js(old('reporter_subject_id','')),
                reporter_staff_unit_id: @js(old('reporter_staff_unit_id','')),
                reporter_phone: @js(old('reporter_phone','')),
                reporter_email: @js(old('reporter_email','')),
            },
            step2: {
                report_type: @js(old('report_type','violation')),
            },
            step3: {
                title: @js(old('title','')),
                urgency: @js(old('urgency','sedang')),
                incident_date: @js(old('incident_date','')),
                related_class_id: @js(old('related_class_id','')),
                alleged_actor_name: @js(old('alleged_actor_name','')),
                alleged_actor_class_id: @js(old('alleged_actor_class_id','')),
                description: @js(old('description','')),
                item_name: @js(old('item_name','')),
                location_id: @js(old('location_id','')),
                damage_condition: @js(old('damage_condition','')),
            },
            step4: {
                consent: @js(old('consent','') ? '1' : ''),
                captcha: @js(old('captcha','')),
            },
        },
        
        init() {
            // Load form data from localStorage on mount
            const savedFormData = sessionStorage.getItem('reportFormData');
            if (savedFormData) {
                try {
                    this.formData = JSON.parse(savedFormData);
                    // Sync type and reporter from formData to component state
                    this.type = this.formData.step2.report_type;
                    this.reporter = this.formData.step1.reporter_type;
                } catch (e) {
                    console.warn('Failed to parse saved form data:', e);
                }
            }
        },
        
        saveFormState() {
            sessionStorage.setItem('reportFormData', JSON.stringify(this.formData));
        },
        
        clearFormState() {
            sessionStorage.removeItem('reportFormData');
        },
        
        fieldLabel(input) {
            if (!input) return 'field wajib';

            const label = input.id
                ? document.querySelector(`label[for="${input.id}"]`)
                : null;

            const raw =
                label?.textContent
                || input.getAttribute('aria-label')
                || input.name
                || 'field wajib';

            return raw
                .replace(/\s*\*\s*$/, '')
                .trim();
        },

        syncConditionalFields() {
            this.$nextTick(() => {
                document.querySelectorAll('[name="reporter_class_id"],[name="reporter_absence_number"]').forEach((el) => el.disabled = this.reporter !== 'siswa');
                document.querySelectorAll('[name="reporter_subject_id"]').forEach((el) => el.disabled = this.reporter !== 'guru');
                document.querySelectorAll('[name="reporter_staff_unit_id"]').forEach((el) => el.disabled = this.reporter !== 'staff');
                // Sinkronkan visibilitas field kondisional + tipe laporan ke JS murni
                // (tetap bekerja meski Alpine dipakai untuk draft formData).
                if (window.LaporinWizard) {
                    window.LaporinWizard.setReporter(this.reporter);
                    window.LaporinWizard.setReportType(this.type);
                }
            });
        },
        validateAttachments(event) {
            const input = event.target;
            const allowed = ['jpg','jpeg','png','webp','pdf'];
            input.setCustomValidity('');
            if (input.files.length > 3) input.setCustomValidity('Maksimal 3 file.');
            for (const file of input.files) {
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                if (!allowed.includes(ext)) input.setCustomValidity('File hanya JPG, PNG, WEBP, atau PDF.');
                if (file.size > 4 * 1024 * 1024) input.setCustomValidity('Ukuran maksimal 4MB per file.');
            }
            if (!input.checkValidity()) {
                input.reportValidity();
                this.stepError = input.validationMessage;
                this.$nextTick(() => {
                    const errorAlert = document.getElementById('step-error-alert');
                    if (errorAlert) {
                        errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            }
        },
    };
};
</script>
@endpush
