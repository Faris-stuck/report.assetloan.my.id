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
    $step4Fields = ['consent','captcha'];
    // Step 3 fields (Detail) — ringkasan pelanggaran
    $step3Fields = ['title','urgency','related_class_id','alleged_actor_name','alleged_actor_class_id','description'];
    // Step 2 fields (Jenis)
    $step2Fields = ['report_type'];
    // Determine which step to start on based on errors
    $initialStep = count(array_intersect($errorKeys, $step4Fields)) ? 4
        : (count(array_intersect($errorKeys, $step3Fields)) ? 3
        : (count(array_intersect($errorKeys, $step2Fields)) ? 2 : 1));

    // Anti-duplikat: jika token sudah kosong, form dinonaktifkan
    $formDisabled = !session()->has('report_submit_token');
@endphp

{{-- ANTI-DUPLIKAT: jika sudah pernah submit, tampilkan pesan封锁 --}}
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
            <h1 class="page-title display-6 mt-3">Lapor Perundungan, Pembullyan, Pelanggaran, atau Kerusakan Fasilitas</h1>
            <p class="page-subtitle fs-6">Gunakan LAPORIN untuk melaporkan kejadian dengan aman, jelas, dan terlacak. Setelah dikirim, simpan nomor laporan dan kode akses untuk tracking.</p>
        </div>
        <div class="col-lg-4">
            <div class="laporin-card bg-white h-100">
                <div class="d-flex gap-3 align-items-start mb-3"><span class="menu-icon">1</span><div><strong>Tanpa login</strong><div class="small-muted">Form aman untuk pelapor.</div></div></div>
                <div class="d-flex gap-3 align-items-start mb-3"><span class="menu-icon">2</span><div><strong>Validasi bertahap</strong><div class="small-muted">Field wajib langsung ditandai.</div></div></div>
                <div class="d-flex gap-3 align-items-start"><span class="menu-icon">3</span><div><strong>Tracking mudah</strong><div class="small-muted">Butuh nomor + kode akses.</div></div></div>
            </div>
        </div>
    </div>
</div>

<div class="laporin-card mb-4" id="alur-validasi">
    <div class="d-flex justify-content-between flex-wrap gap-2 align-items-center mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Alur & Validasi</h2>
            <div class="small-muted">Setiap tahap punya validasi. Field yang tidak sesuai akan ditandai.</div>
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
            <h2 class="h4 fw-bold">Kanal Lapor Perundungan dan Pelanggaran SMK Taruna Bangsa Bekasi</h2>
            <p class="mb-2">LAPORIN digunakan untuk laporan pembullyan, perundungan, pelanggaran tata tertib, atau kerusakan fasilitas yang membutuhkan tindak lanjut sekolah.</p>
            <p class="small-muted mb-0">Baca panduan lengkap sebelum mengisi di <a href="{{ route('seo.bullying-guide') }}">Panduan Lapor</a>.</p>
        </div>
        <div class="col-lg-4">
            <div class="d-grid gap-2">
                <a class="btn btn-outline-laporin" href="{{ route('seo.bullying-guide') }}">Panduan Lapor</a>
                <a class="btn btn-outline-laporin" href="{{ route('seo.faq') }}">FAQ</a>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- FORM UTAMA                                                     --}}
{{-- ============================================================ --}}
<form id="form-laporan" method="POST" action="{{ route('public.report.store') }}" enctype="multipart/form-data" x-data="reportWizard()" x-init="syncConditionalFields()" @submit="if (!validateCurrentStep()) $event.preventDefault()">
@csrf
<input type="hidden" name="report_submit_token" value="{{ session('report_submit_token') }}">
<input type="hidden" name="qr_code_id" value="{{ $qrCode?->id }}">

{{-- Step tracker --}}
<div class="laporin-card mb-3 step-track">
    <div class="row g-2 text-center">
        @foreach([1=>'Identitas',2=>'Jenis',3=>'Detail',4=>'Kirim'] as $n=>$label)
            <div class="col">
                <div class="step-dot" :class="step >= {{ $n }} ? 'active' : ''">{{ $n }}</div>
                <div class="small mt-2 fw-semibold">{{ $label }}</div>
            </div>
        @endforeach
    </div>
</div>

<div class="invalid-step-hint mb-3" x-show="stepError" x-text="stepError" x-cloak></div>

<div class="laporin-card wizard-panel p-4 p-lg-5">

{{-- ============================================================ --}}
{{-- LANGKAH 1: IDENTITAS                                          --}}
{{-- ============================================================ --}}
<section x-show="step===1" data-step="1" x-cloak>
    <span class="page-kicker">Langkah 1</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Identitas Pelapor</h2>
    <p class="small-muted mb-4">Data pelapor membantu sekolah menghubungi bila ada yang perlu dikonfirmasi.</p>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label required" for="reporter_type">Jenis Pelapor</label>
            <select id="reporter_type" name="reporter_type" class="form-select" x-model="reporter" @change="syncConditionalFields()" required>
                <option value="siswa">Siswa</option>
                <option value="guru">Guru</option>
                <option value="staff">Staf</option>
            </select>
        </div>
        <div class="col-md-8">
            <label class="form-label required" for="reporter_name">Nama Pelapor</label>
            <input id="reporter_name" name="reporter_name" value="{{ old('reporter_name') }}" class="form-control" required maxlength="150" autocomplete="name" placeholder="Nama lengkap">
        </div>

        {{-- Siswa --}}
        <div class="col-md-6" x-show="reporter==='siswa'" x-cloak>
            <label class="form-label required" for="reporter_class_id">Kelas</label>
            <select id="reporter_class_id" name="reporter_class_id" class="form-select" :required="reporter==='siswa'" :disabled="reporter!=='siswa'">
                <option value="">Pilih kelas</option>
                @include('public.partials.class-options', ['selectedClassId' => old('reporter_class_id')])
            </select>
            <div class="helper-text">Dikelompokkan per jurusan dan diurutkan.</div>
        </div>
        <div class="col-md-6" x-show="reporter==='siswa'" x-cloak>
            <label class="form-label" for="reporter_absence_number">No. Absen</label>
            <input id="reporter_absence_number" type="number" name="reporter_absence_number" value="{{ old('reporter_absence_number') }}" min="1" max="60" class="form-control" :disabled="reporter!=='siswa'" placeholder="1–60">
        </div>

        {{-- Guru --}}
        <div class="col-md-6" x-show="reporter==='guru'" x-cloak>
            <label class="form-label required" for="reporter_subject_id">Mata Pelajaran</label>
            <select id="reporter_subject_id" name="reporter_subject_id" class="form-select" :required="reporter==='guru'" :disabled="reporter!=='guru'">
                <option value="">Pilih mapel</option>
                @foreach($subjects as $s)<option value="{{ $s->id }}" @selected(old('reporter_subject_id') == $s->id)>{{ $s->subject_name }}</option>@endforeach
            </select>
        </div>

        {{-- Staf --}}
        <div class="col-md-6" x-show="reporter==='staff'" x-cloak>
            <label class="form-label required" for="reporter_staff_unit_id">Unit Staf</label>
            <select id="reporter_staff_unit_id" name="reporter_staff_unit_id" class="form-select" :required="reporter==='staff'" :disabled="reporter!=='staff'">
                <option value="">Pilih unit</option>
                @foreach($staffUnits as $u)<option value="{{ $u->id }}" @selected(old('reporter_staff_unit_id') == $u->id)>{{ $u->unit_name }}</option>@endforeach
            </select>
        </div>

        {{-- No. HP wajib, email opsional --}}
        <div class="col-md-6">
            <label class="form-label required" for="reporter_phone">No. HP</label>
            <input id="reporter_phone" name="reporter_phone" value="{{ old('reporter_phone') }}" class="form-control" required maxlength="30" pattern="[0-9+() .*\-]+" inputmode="tel" autocomplete="tel" aria-describedby="reporter_phone_help" placeholder="Contoh: 0812 3456 7890">
            <div id="reporter_phone_help" class="helper-text">Nomor HP wajib diisi. Gunakan 8-15 digit.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="reporter_email">Email</label>
            <input id="reporter_email" type="email" name="reporter_email" value="{{ old('reporter_email') }}" class="form-control" maxlength="150" autocomplete="email" placeholder="Contoh: nama@email.com" aria-describedby="reporter_email_help">
            <div id="reporter_email_help" class="helper-text">Opsional. Jika diisi, email digunakan untuk notifikasi status laporan.</div>
        </div>
    </div>
</section>

{{-- ============================================================ --}}
{{-- LANGKAH 2: JENIS LAPORAN                                      --}}
{{-- ============================================================ --}}
<section x-show="step===2" data-step="2" x-cloak>
    <span class="page-kicker">Langkah 2</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Pilih Jenis Laporan</h2>
    <p class="small-muted mb-4">Pilih jenis laporan yang sesuai.</p>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="choice-card p-4 w-100">
                <input type="radio" name="report_type" value="violation" x-model="type" @change="syncConditionalFields()" required>
                <strong class="d-block mt-2">Perundungan / Pelanggaran</strong>
                <span class="small-muted">Untuk perundungan, pembullyan, atau pelanggaran tata tertib. Ditangani oleh Kesiswaan.</span>
            </label>
        </div>
        <div class="col-md-6">
            <label class="choice-card p-4 w-100">
                <input type="radio" name="report_type" value="damage" x-model="type" @change="syncConditionalFields()" required>
                <strong class="d-block mt-2">Kerusakan Fasilitas</strong>
                <span class="small-muted">Untuk kerusakan meja, proyektor, AC, toilet, pintu, dll. Ditangani oleh Sarpras.</span>
            </label>
        </div>
    </div>
</section>

{{-- ============================================================ --}}
{{-- LANGKAH 3: DETAIL (RINGKAS UNTUK PELANGGARAN)                --}}
{{-- ============================================================ --}}
<section x-show="step===3" data-step="3" x-cloak>
    <span class="page-kicker">Langkah 3</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Detail Kejadian</h2>
    <p class="small-muted mb-4">Isi sesuai kejadian yang dilaporkan.</p>
    <div class="row g-3">

        {{-- FIELD UNIVERSAL: Judul --}}
        <div class="col-md-8">
            <label class="form-label required" for="title">Judul</label>
            <input id="title" name="title" value="{{ old('title') }}" class="form-control" required maxlength="200"
                :placeholder="type==='violation' ? 'Contoh: Perundungan di Lab Komputer' : 'Contoh: Lampu kelas X Mati'">
        </div>

        {{-- FIELD UNIVERSAL: Urgensi --}}
        <div class="col-md-4">
            <label class="form-label required" for="urgency">Tingkat Urgensi</label>
            <select id="urgency" name="urgency" class="form-select" required>
                @foreach(['rendah','sedang','tinggi','darurat'] as $urgency)
                    <option value="{{ $urgency }}" @selected(old('urgency','sedang') === $urgency)>
                        {{ match($urgency) { 'rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi', 'darurat' => 'Darurat' } }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- ======================================================== --}}
        {{-- VIOLATION: 4 FIELD RINGKAS                                --}}
        {{-- ======================================================== --}}
        <template x-if="type==='violation'">
            <div class="col-12">
                <div class="detail-box">
                    <h3 class="h6 fw-bold mb-3">Detail Perundungan / Pelanggaran</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="alleged_actor_name">Nama Terduga Pelaku</label>
                            <input id="alleged_actor_name" name="alleged_actor_name" value="{{ old('alleged_actor_name') }}" class="form-control" maxlength="150" placeholder="Isi jika diketahui" :disabled="type!=='violation'">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="related_class_id">Kelas Pelaku</label>
                            <select id="related_class_id" name="related_class_id" class="form-select" :required="type==='violation'" :disabled="type!=='violation'">
                                <option value="">Pilih kelas</option>
                                @include('public.partials.class-options', ['selectedClassId' => old('related_class_id')])
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="victim_class_id">Kelas Korban</label>
                            <select id="victim_class_id" name="victim_class_id" class="form-select" :disabled="type!=='violation'">
                                <option value="">Pilih kelas jika diketahui</option>
                                @include('public.partials.class-options', ['selectedClassId' => old('victim_class_id')])
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="alleged_actor_class_id">Kelas Terduga Pelaku</label>
                            <select id="alleged_actor_class_id" name="alleged_actor_class_id" class="form-select" :disabled="type!=='violation'">
                                <option value="">Pilih kelas jika berbeda</option>
                                @include('public.partials.class-options', ['selectedClassId' => old('alleged_actor_class_id')])
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label required" for="description">Kronologi</label>
                            <textarea id="description" name="description" class="form-control" rows="5" required maxlength="5000"
                                placeholder="Jelaskan apa yang terjadi, kapan, di mana, dan siapa saja yang terlibat."
                                :disabled="type!=='violation'">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- ======================================================== --}}
        {{-- DAMAGE: FIELD LENGKAP                                    --}}
        {{-- ======================================================== --}}
        <template x-if="type==='damage'">
            <div class="col-12">
                <div class="detail-box">
                    <h3 class="h6 fw-bold mb-3">Detail Kerusakan</h3>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label required" for="item_name">Nama Barang / Fasilitas</label>
                            <input id="item_name" name="item_name" value="{{ old('item_name') }}" class="form-control" placeholder="Contoh: Proyektor, AC, Pintu" maxlength="150" :required="type==='damage'" :disabled="type!=='damage'">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="item_category">Kategori</label>
                            <input id="item_category" name="item_category" value="{{ old('item_category') }}" class="form-control" placeholder="Contoh: Elektronik" maxlength="100" :disabled="type!=='damage'">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="priority_damage">Prioritas Perbaikan</label>
                            <select id="priority_damage" name="priority" class="form-select" :disabled="type!=='damage'">
                                @foreach(['rendah','sedang','tinggi','darurat'] as $p)
                                    <option value="{{ $p }}" @selected(old('priority','sedang') === $p)>
                                        {{ match($p) { 'rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi', 'darurat' => 'Darurat' } }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="location_id_damage">Lokasi</label>
                            <select id="location_id_damage" name="location_id" class="form-select" :disabled="type!=='damage'">
                                <option value="">Pilih lokasi</option>
                                @foreach($locations as $l)<option value="{{ $l->id }}" @selected(old('location_id') == $l->id)>{{ $l->location_name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="custom_location_damage">Lokasi Lainnya</label>
                            <input id="custom_location_damage" name="custom_location" value="{{ old('custom_location') }}" class="form-control" maxlength="150" placeholder="Contoh: Koridor Lantai 2" :disabled="type!=='damage'">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="damage_condition">Kondisi Kerusakan</label>
                            <textarea id="damage_condition" name="damage_condition" class="form-control" rows="4" placeholder="Jelaskan bagian yang rusak dan kondisi terakhir" maxlength="2000" :required="type==='damage'" :disabled="type!=='damage'">{{ old('damage_condition') }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="suspected_cause">Dugaan Penyebab</label>
                            <textarea id="suspected_cause" name="suspected_cause" class="form-control" rows="4" placeholder="Isi jika penyebab diketahui" maxlength="1000" :disabled="type!=='damage'">{{ old('suspected_cause') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label required" for="description_damage">Deskripsi Dampak</label>
                            <textarea id="description_damage" name="description" class="form-control" rows="4" required maxlength="5000" placeholder="Jelaskan dampak kerusakan bagi kegiatan belajar atau operasional." :disabled="type!=='damage'">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </template>

    </div>
</section>

{{-- ============================================================ --}}
{{-- LANGKAH 4: KONFIRMASI & KIRIM                                  --}}
{{-- ============================================================ --}}
<section x-show="step===4" data-step="4" x-cloak>
    <span class="page-kicker">Langkah 4</span>
    <h2 class="h4 fw-bold mt-2 mb-1">Konfirmasi & Kirim</h2>
    <p class="small-muted mb-4">Pastikan data benar. Setelah kirim, Anda tidak bisa mengirim laporan lagi dari sesi ini.</p>

    {{-- Lampiran dipindah ke step akhir --}}
    <div class="detail-box mb-3">
        <h3 class="h6 fw-bold mb-3">Bukti Foto / Dokumen (Opsional)</h3>
        <label class="form-label" for="attachments">Upload Bukti</label>
        <input id="attachments" type="file" name="attachments[]" class="form-control" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" @change="validateAttachments($event)">
        <div class="helper-text">Maksimal 3 file; JPG, PNG, WEBP, atau PDF; maksimal 4MB per file.</div>
    </div>

    <div class="detail-box mb-3">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" required>
            <label class="form-check-label required" for="consent">Saya menyatakan laporan ini benar dan bersedia dihubungi sekolah bila diperlukan.</label>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label required" for="captcha">CAPTCHA: berapa {{ $captchaQuestion }}?</label>
        <input id="captcha" name="captcha" class="form-control" required inputmode="numeric" pattern="[0-9]+" maxlength="2" placeholder="Jawaban angka">
    </div>
</section>

</div>{{-- end .wizard-panel --}}

<div class="bottom-action">
    <div class="d-flex justify-content-between gap-2">
        <button type="button" class="btn btn-outline-secondary" x-show="step>1" @click="step--; stepError=''">Kembali</button>
        <span class="d-none d-sm-inline small-muted align-self-center" x-text="`Langkah ${step} dari 4`"></span>
        <button type="button" class="btn btn-laporin" x-show="step<4" @click="next()">Lanjut</button>
        <button type="submit" class="btn btn-laporin" x-show="step===4">Kirim Laporan</button>
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
window.reportWizard = function () {
    return {
        step: {{ $initialStep }},
        type: @js(old('report_type','violation')),
        reporter: @js(old('reporter_type','siswa')),
        stepError: '',
        next() {
            if (this.validateCurrentStep()) {
                this.stepError = '';
                this.step++;
                this.$nextTick(() => {
                    document.getElementById('form-laporan')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
        },
        validateCurrentStep() {
            this.stepError = '';
            const section = this.$root.querySelector(`[data-step="${this.step}"]`);
            if (!section) return true;

            // Validasi step 3 violation ringkas
            if (this.step === 3 && this.type === 'violation') {
                const title = document.getElementById('title');
                const actorClass = document.getElementById('related_class_id');
                const desc = document.getElementById('description');
                const fields = [title, actorClass, desc].filter(f => f && !f.disabled);
                const firstInvalid = fields.find((el) => !el.checkValidity());
                if (firstInvalid) {
                    firstInvalid.reportValidity();
                    this.stepError = 'Lengkapi field wajib pada langkah ini.';
                    return false;
                }
                return true;
            }

            // Validasi step 3 damage
            if (this.step === 3 && this.type === 'damage') {
                const itemName = document.getElementById('item_name');
                const damageCondition = document.getElementById('damage_condition');
                const description = document.getElementById('description_damage');
                const fields = [itemName, damageCondition, description].filter(f => f && !f.disabled);
                const firstInvalid = fields.find((el) => !el.checkValidity());
                if (firstInvalid) {
                    firstInvalid.reportValidity();
                    this.stepError = 'Lengkapi field wajib pada langkah ini.';
                    return false;
                }
                return true;
            }

            const controls = [...section.querySelectorAll('input,select,textarea')].filter((el) => !el.disabled);
            const firstInvalid = controls.find((el) => !el.checkValidity());
            if (firstInvalid) {
                firstInvalid.reportValidity();
                this.stepError = 'Lengkapi field wajib atau perbaiki format.';
                return false;
            }
            return true;
        },
        syncConditionalFields() {
            this.$nextTick(() => {
                this.$root.querySelectorAll('[name="reporter_class_id"],[name="reporter_absence_number"]').forEach((el) => el.disabled = this.reporter !== 'siswa');
                this.$root.querySelectorAll('[name="reporter_subject_id"]').forEach((el) => el.disabled = this.reporter !== 'guru');
                this.$root.querySelectorAll('[name="reporter_staff_unit_id"]').forEach((el) => el.disabled = this.reporter !== 'staff');
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
            if (!input.checkValidity()) { input.reportValidity(); this.stepError = input.validationMessage; }
        },
    };
};
</script>
@endpush
