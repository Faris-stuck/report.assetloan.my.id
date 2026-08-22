@extends('layouts.app')
@section('title','Kesiswaan')
@section('content')
@php
    $processable = ['menunggu_verifikasi','memerlukan_informasi','dibuka_kembali'];

    // Dipakai untuk membedakan "belum ada laporan sama sekali" dari "filter
    // terlalu sempit". Keduanya dulu berbunyi "Belum ada data." sehingga petugas
    // tidak tahu harus melebarkan filter atau memang tidak ada yang perlu
    // ditangani.
    $hasActiveFilter = request('search') || request('status') || request('from_date') || request('to_date');

    $statusLabels = [
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'memerlukan_informasi' => 'Perlu Informasi Tambahan',
        'dibuka_kembali' => 'Dibuka Kembali',
        'sedang_ditangani' => 'Sedang Ditangani',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi Pelapor',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ];

    // "Proses Laporan" mewajibkan student_id dan violation_type_id. Kalau salah
    // satu master datanya kosong, tombol submit tidak akan pernah bisa dipakai
    // dan browser hanya menampilkan "Please select an item in the list" tanpa
    // menjelaskan penyebabnya. Deteksi di sini supaya bisa dijelaskan.
    $missingMaster = [];
    if ($students->isEmpty()) { $missingMaster[] = 'data siswa'; }
    if ($types->isEmpty()) { $missingMaster[] = 'jenis pelanggaran'; }
    $canProcess = $missingMaster === [];

    // KesiswaanService mengembalikan back()->withErrors()->withInput() untuk
    // ketiga aksi, tetapi halaman ini merender 15 laporan sekaligus dan semua
    // formnya memakai nama field yang sama (student_id, violation_type_id,
    // note, reason). Tanpa penanda baris:
    //   1. tidak ada satu pun old() yang bisa dipulihkan dengan aman — alasan
    //      penolakan sepanjang dua ribu karakter hilang total dan harus
    //      ditulis ulang;
    //   2. skrip di layouts/app.blade.php menempelkan is-invalid pada
    //      [name="..."] PERTAMA di halaman, jadi pesan error laporan ke-7
    //      muncul di laporan ke-1 dan menuduh baris yang salah.
    // __form dan __report_id ikut terkirim lalu dibaca kembali di sini. Server
    // memakai $request->validate() yang hanya mengembalikan key tervalidasi,
    // jadi dua field tambahan ini tidak pernah sampai ke Eloquent.
    $failedForm = old('__form');
    $failedReportId = (int) old('__report_id');
@endphp
<div class="page-header">
    <div>
        <span class="page-kicker">Kesiswaan</span>
        <h1 class="page-title h2 mt-2">Validasi Pelanggaran Siswa</h1>
        <p class="page-subtitle">Cek laporan, pilih siswa yang terbukti, lalu simpan jenis pelanggaran agar poin siswa berkurang sesuai aturan.</p>
    </div>
</div>
<div class="laporin-card card-soft mb-4">
    <h2 class="h5 fw-bold mb-3">Alur Kesiswaan</h2>
    <div class="flowchart compact"><div class="flow-node">Laporan Masuk</div><div class="flow-node">Bukti Dicek</div><div class="flow-node">Pilih Siswa</div><div class="flow-node">Pilih Pelanggaran</div><div class="flow-node">Kesiswaan Menangani</div><div class="flow-node">Pelapor Konfirmasi</div></div>
</div>

<!-- Search & Filter Card -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('kesiswaan.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-4">
            <label class="form-label" for="search">Cari</label>
            <input id="search" name="search" type="text" class="form-control"
                   placeholder="Cari nomor, judul, atau deskripsi laporan..." value="{{ request('search') }}" maxlength="100">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="status">Status</label>
            <select id="status" name="status" class="form-select">
                <option value="">Semua</option>
                <option value="menunggu_verifikasi" @selected(request('status') === 'menunggu_verifikasi')>Menunggu Verifikasi</option>
                <option value="memerlukan_informasi" @selected(request('status') === 'memerlukan_informasi')>Perlu Informasi</option>
                <option value="dibuka_kembali" @selected(request('status') === 'dibuka_kembali')>Dibuka Kembali</option>
                <option value="sedang_ditangani" @selected(request('status') === 'sedang_ditangani')>Sedang Ditangani</option>
                <option value="menunggu_konfirmasi" @selected(request('status') === 'menunggu_konfirmasi')>Menunggu Konfirmasi</option>
                <option value="selesai" @selected(request('status') === 'selesai')>Selesai</option>
                <option value="ditolak" @selected(request('status') === 'ditolak')>Ditolak</option>
            </select>
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="from_date">Dari</label>
            {{-- max/min saling mengunci supaya rentang terbalik (Dari lebih baru
                 daripada Sampai) tidak lolos. Rentang mustahil menghasilkan nol
                 baris, dan petugas hanya melihat daftar kosong tanpa tahu
                 rentangnyalah penyebabnya. --}}
            <input id="from_date" name="from_date" type="date" class="form-control" value="{{ request('from_date') }}"
                   @if(request('to_date')) max="{{ request('to_date') }}" @endif data-range-start>
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="to_date">Sampai</label>
            <input id="to_date" name="to_date" type="date" class="form-control" value="{{ request('to_date') }}"
                   @if(request('from_date')) min="{{ request('from_date') }}" @endif data-range-end>
        </div>

        <div class="col-md-6 col-lg-2 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
            <a href="{{ route('kesiswaan.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Results Info -->
@if($hasActiveFilter)
    <div class="laporin-card mb-3 pb-3 border-bottom">
        <p class="text-muted small mb-0">
            Menampilkan {{ $reports->count() }} dari {{ $reports->total() }} laporan
            @if(request('search'))
                untuk pencarian "<strong>{{ request('search') }}</strong>"
            @endif
        </p>
    </div>
@endif

<div class="report-card-list">
@forelse($reports as $r)
    @php
        // Hanya baris yang benar-benar gagal divalidasi yang boleh memulihkan
        // old() dan menampilkan tanda merah. Baris lain harus tetap bersih.
        $processFailed = $failedForm === 'process' && $failedReportId === $r->id;
        $rejectFailed = $failedForm === 'reject' && $failedReportId === $r->id;
        $completeFailed = $failedForm === 'complete' && $failedReportId === $r->id;
        $rowFailed = $processFailed || $rejectFailed || $completeFailed;
    @endphp
    <article class="report-row-card" id="report-card-{{ $r->id }}">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <div><a class="fw-bold" href="{{ route('reports.show',$r) }}">{{ $r->report_number }}</a><h2 class="h5 mb-1">{{ $r->title }}</h2><div class="report-meta"><span>{{ $r->created_at->format('d/m/Y H:i') }}</span><span>Pelanggaran siswa</span></div></div>
            <span class="status-pill status-{{ $r->status }}">{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</span>
        </div>
        @if(in_array($r->status, $processable, true))
            <div class="accordion" id="accordion-kesiswaan-{{ $r->id }}">
                <!-- Process Tab -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        {{-- data-bs-parent hanya mengizinkan satu panel terbuka.
                             Kalau penolakan yang gagal, panel Tolak-lah yang
                             harus terbuka, kalau tidak operator melihat spanduk
                             error tanpa pernah menemukan field penyebabnya. --}}
                        <button class="accordion-button {{ $rejectFailed ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#process-{{ $r->id }}" aria-expanded="{{ $rejectFailed ? 'false' : 'true' }}" aria-controls="process-{{ $r->id }}">
                            Proses Laporan
                        </button>
                    </h2>
                    <div id="process-{{ $r->id }}" class="accordion-collapse collapse {{ $rejectFailed ? '' : 'show' }}" data-bs-parent="#accordion-kesiswaan-{{ $r->id }}">
                        <div class="accordion-body">
                            <form method="POST" action="{{ route('kesiswaan.process',$r) }}" class="row g-3 align-items-end">@csrf
                                <input type="hidden" name="__form" value="process">
                                <input type="hidden" name="__report_id" value="{{ $r->id }}">
                                @unless($canProcess)
                                    <div class="col-12">
                                        <div class="alert alert-warning mb-0" role="alert">
                                            Laporan belum bisa diproses karena {{ implode(' dan ', $missingMaster) }} masih kosong.
                                            Minta Superadmin melengkapinya lewat menu Data Master terlebih dahulu.
                                            Laporan ini tetap bisa ditolak bila memang tidak valid.
                                        </div>
                                    </div>
                                @endunless

                                {{-- KesiswaanProcessor melempar error dengan key
                                     'report' (status sudah berubah, poin sudah
                                     dipotong, tipe laporan salah). Tidak ada
                                     field bernama itu, jadi tanpa blok ini
                                     pesannya hanya muncul di spanduk atas tanpa
                                     menyebut laporan mana yang dimaksud. --}}
                                @if($processFailed)
                                    @error('report')
                                        <div class="col-12">
                                            <div class="alert alert-danger mb-0" role="alert">{{ $message }}</div>
                                        </div>
                                    @enderror
                                @endif

                                <div class="col-lg-6">
                                    <label class="form-label required" for="student_id_{{ $r->id }}">Siswa yang terbukti</label>
                                    <select
                                        id="student_id_{{ $r->id }}"
                                        name="student_id"
                                        class="form-select @if($processFailed)@error('student_id') is-invalid @enderror @endif"
                                        @if($processFailed) @error('student_id') aria-invalid="true" @enderror @endif
                                        required
                                    >
                                        <option value="">Pilih siswa</option>
                                        @foreach($students as $s)
                                            <option value="{{ $s->id }}" @selected($processFailed && (int) old('student_id') === $s->id)>{{ $s->name }} - {{ $s->class?->class_name }}</option>
                                        @endforeach
                                    </select>
                                    @if($processFailed)@error('student_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                </div>

                                <div class="col-lg-6">
                                    <label class="form-label required" for="violation_type_id_{{ $r->id }}">Jenis pelanggaran</label>
                                    <select
                                        id="violation_type_id_{{ $r->id }}"
                                        name="violation_type_id"
                                        class="form-select @if($processFailed)@error('violation_type_id') is-invalid @enderror @endif"
                                        @if($processFailed) @error('violation_type_id') aria-invalid="true" @enderror @endif
                                        required
                                    >
                                        <option value="">Pilih jenis</option>
                                        @foreach($types as $t)
                                            <option value="{{ $t->id }}" @selected($processFailed && (int) old('violation_type_id') === $t->id)>{{ $t->violation_name }} (-{{ $t->point_reduction }} poin)</option>
                                        @endforeach
                                    </select>
                                    @if($processFailed)@error('violation_type_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                </div>

                                <div class="col-12">
                                    <label class="form-label" for="note_{{ $r->id }}">Catatan pembinaan</label>
                                    <textarea
                                        id="note_{{ $r->id }}"
                                        name="note"
                                        class="form-control @if($processFailed)@error('note') is-invalid @enderror @endif"
                                        placeholder="Opsional"
                                        maxlength="2000"
                                        rows="3"
                                    >{{ $processFailed ? old('note') : '' }}</textarea>
                                    @if($processFailed)@error('note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                </div>

                                <div class="col-12"><button type="submit" class="btn btn-laporin" aria-label="Proses laporan #{{ $r->report_number }}" @disabled(! $canProcess)>Proses Laporan</button></div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Reject Tab -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button {{ $rejectFailed ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#reject-{{ $r->id }}" aria-expanded="{{ $rejectFailed ? 'true' : 'false' }}" aria-controls="reject-{{ $r->id }}">
                            Tolak Laporan
                        </button>
                    </h2>
                    <div id="reject-{{ $r->id }}" class="accordion-collapse collapse {{ $rejectFailed ? 'show' : '' }}" data-bs-parent="#accordion-kesiswaan-{{ $r->id }}">
                        <div class="accordion-body">
                            <form method="POST" action="{{ route('kesiswaan.reject',$r) }}" class="row g-3" onsubmit="return confirm('Tolak laporan ini? Alur laporan akan berhenti.')">@csrf
                                <input type="hidden" name="__form" value="reject">
                                <input type="hidden" name="__report_id" value="{{ $r->id }}">

                                @if($rejectFailed)
                                    @error('report')
                                        <div class="col-12">
                                            <div class="alert alert-danger mb-0" role="alert">{{ $message }}</div>
                                        </div>
                                    @enderror
                                @endif

                                <div class="col-12">
                                    <label class="form-label required" for="reject_reason_{{ $r->id }}">Alasan penolakan</label>
                                    <textarea
                                        id="reject_reason_{{ $r->id }}"
                                        name="reason"
                                        class="form-control @if($rejectFailed)@error('reason') is-invalid @enderror @endif"
                                        @if($rejectFailed) @error('reason') aria-invalid="true" @enderror @endif
                                        placeholder="Wajib diisi jika laporan ditolak"
                                        required
                                        maxlength="2000"
                                        rows="3"
                                    >{{ $rejectFailed ? old('reason') : '' }}</textarea>
                                    @if($rejectFailed)@error('reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                                </div>

                                <div class="col-12"><button type="submit" class="btn btn-outline-danger" aria-label="Tolak laporan #{{ $r->report_number }}">Tolak Laporan</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @elseif($r->status === 'sedang_ditangani')
            <form method="POST" action="{{ route('kesiswaan.complete', $r) }}" class="row g-3 align-items-end" onsubmit="return confirm('Tandai penanganan Kesiswaan selesai dan minta konfirmasi pelapor?')">
                @csrf
                <input type="hidden" name="__form" value="complete">
                <input type="hidden" name="__report_id" value="{{ $r->id }}">

                @if($completeFailed)
                    @error('report')
                        <div class="col-12">
                            <div class="alert alert-danger mb-0" role="alert">{{ $message }}</div>
                        </div>
                    @enderror
                @endif

                <div class="col-12">
                    <label class="form-label" for="completion_note_{{ $r->id }}">Catatan penyelesaian Kesiswaan</label>
                    <textarea
                        id="completion_note_{{ $r->id }}"
                        name="note"
                        class="form-control @if($completeFailed)@error('note') is-invalid @enderror @endif"
                        maxlength="2000"
                        placeholder="Ringkasan pembinaan atau tindak lanjut (opsional)"
                        rows="3"
                    >{{ $completeFailed ? old('note') : '' }}</textarea>
                    @if($completeFailed)@error('note')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror @endif
                </div>
                <div class="col-12"><button type="submit" class="btn btn-laporin">Selesaikan Penanganan</button></div>
            </form>
        @else
            <div class="status-note mb-0">
                Status laporan saat ini: <strong>{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</strong>.
                Tidak ada aksi Kesiswaan yang perlu dilakukan di tahap ini.
            </div>
        @endif
    </article>
@empty
    <div class="laporin-card text-center py-5 text-muted">
        @if($hasActiveFilter)
            Tidak ada laporan yang cocok dengan filter ini.
            <a href="{{ route('kesiswaan.index') }}">Reset filter</a> untuk melihat seluruh laporan.
        @else
            Belum ada laporan pelanggaran yang masuk.
        @endif
    </div>
@endforelse
</div>
<div class="mt-3">{{ $reports->appends(request()->query())->links() }}</div>
@endsection

@if($failedForm && $failedReportId)
@push('scripts')
<script>
    // Skrip di layouts/app.blade.php menempelkan is-invalid dan satu
    // .server-validation-feedback pada [name="..."] PERTAMA di dokumen. Di
    // halaman ini nama field terulang di setiap laporan, jadi penanda itu
    // hampir pasti mendarat di kartu yang salah — operator melihat laporan
    // teratas ditandai merah padahal yang gagal laporan lain, dan pesan
    // "poin sudah dipotong untuk X" jadi menuduh siswa yang tidak dipilihnya.
    //
    // Umpan balik yang benar sudah dirender server-side di dalam kartu yang
    // gagal (lihat blok invalid-feedback di atas), sehingga penanda otomatis
    // dari layout SELALU berlebihan di halaman ini: di luar kartu yang gagal ia
    // menuduh baris yang salah, dan di dalam kartu itu ia menampilkan pesan yang
    // sama dua kali di bawah satu field. Karena itu semuanya dibuang, lalu fokus
    // digeser ke field yang benar. Listener layout terdaftar lebih dulu karena
    // script-nya berada sebelum stack scripts, sehingga saat kode ini berjalan
    // penandanya sudah ada dan bisa dibersihkan.
    //
    // Catatan: jangan menulis nama direktif Blade berawalan @ di dalam komentar
    // JS ini. Blade mengompilasi direktif di seluruh berkas, termasuk di dalam
    // tag script, sehingga sebutan direktif tanpa penutupnya akan menghasilkan
    // blok PHP yang tidak tertutup dan seluruh halaman gagal dirender.
    document.addEventListener('DOMContentLoaded', () => {
        const scope = document.getElementById(@js('report-card-'.$failedReportId));

        document.querySelectorAll('.server-validation-feedback').forEach((el) => el.remove());

        document.querySelectorAll('[aria-invalid="true"]').forEach((el) => {
            if (! scope || ! scope.contains(el)) {
                el.classList.remove('is-invalid');
                el.removeAttribute('aria-invalid');
            }
        });

        // Laporan yang gagal bisa berada di halaman paginasi lain; tanpa
        // pengecekan ini querySelector() pada scope null akan melempar.
        if (! scope) {
            return;
        }

        const firstInvalid = scope.querySelector('.is-invalid');

        if (firstInvalid) {
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstInvalid.focus({ preventScroll: true });
        } else {
            scope.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
</script>
@endpush
@endif

@push('scripts')
<script>
    // Atribut min/max yang dirender server hanya mencerminkan nilai request
    // sebelumnya, jadi rentang terbalik masih bisa disusun sebelum submit
    // pertama. Penguncian langsung membuat browser menolak saat tanggal dipilih,
    // bukan setelah halaman kembali dengan daftar kosong.
    document.addEventListener('DOMContentLoaded', () => {
        const start = document.querySelector('[data-range-start]');
        const end = document.querySelector('[data-range-end]');

        if (! start || ! end) {
            return;
        }

        const sync = () => {
            start.max = end.value || '';
            end.min = start.value || '';
        };

        start.addEventListener('change', sync);
        end.addEventListener('change', sync);
        sync();
    });
</script>
@endpush

