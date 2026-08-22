@extends('layouts.app')
@section('title','Sarpras')
@section('content')
@php
    $processable = ['menunggu_verifikasi','memerlukan_informasi','dibuka_kembali','sedang_ditangani'];
    $minSchedule = now()->format('Y-m-d\TH:i');
    $statusLabels = [
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'memerlukan_informasi' => 'Perlu Informasi Tambahan',
        'dibuka_kembali' => 'Dibuka Kembali',
        'sedang_ditangani' => 'Sedang Ditangani',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi Pelapor',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ];
@endphp
<div class="page-header">
    <div>
        <span class="page-kicker">Sarpras</span>
        <h1 class="page-title h2 mt-2">Tindak Lanjut Kerusakan Fasilitas</h1>
        <p class="page-subtitle">Atur prioritas, waktu perbaikan, foto bukti, dan catatan pengerjaan dalam satu halaman.</p>
    </div>
</div>
<div class="laporin-card card-soft mb-4">
    <h2 class="h5 fw-bold mb-3">Alur Sarpras</h2>
    <div class="flowchart compact"><div class="flow-node">Laporan Masuk</div><div class="flow-node">Laporan Ditinjau</div><div class="flow-node">Waktu Perbaikan</div><div class="flow-node">Perbaikan</div><div class="flow-node">Foto Selesai</div><div class="flow-node">Selesai</div></div>
</div>

<!-- Search & Filter Card -->
<div class="laporin-card mb-4">
    <form method="GET" action="{{ route('sarpras.index') }}" class="row g-3 align-items-end">
        <div class="col-md-6 col-lg-3">
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
            <label class="form-label" for="priority">Prioritas</label>
            <select id="priority" name="priority" class="form-select">
                <option value="">Semua</option>
                <option value="rendah" @selected(request('priority') === 'rendah')>Rendah</option>
                <option value="sedang" @selected(request('priority') === 'sedang')>Sedang</option>
                <option value="tinggi" @selected(request('priority') === 'tinggi')>Tinggi</option>
                <option value="darurat" @selected(request('priority') === 'darurat')>Darurat</option>
            </select>
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="from_date">Dari</label>
            <input id="from_date" name="from_date" type="date" class="form-control" value="{{ request('from_date') }}">
        </div>

        <div class="col-md-6 col-lg-2">
            <label class="form-label" for="to_date">Sampai</label>
            <input id="to_date" name="to_date" type="date" class="form-control" value="{{ request('to_date') }}">
        </div>

        <div class="col-md-6 col-lg-1 d-flex gap-2">
            <button type="submit" class="btn btn-laporin flex-grow-1">Cari</button>
            <a href="{{ route('sarpras.index') }}" class="btn btn-outline-secondary">Reset</a>
        </div>
    </form>
</div>

<!-- Results Info -->
@if(request('search') || request('status') || request('priority') || request('from_date') || request('to_date'))
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
    <article class="report-row-card">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <div><a class="fw-bold" href="{{ route('reports.show',$r) }}">{{ $r->report_number }}</a><h2 class="h5 mb-1">{{ $r->title }}</h2><div class="report-meta"><span>{{ $r->created_at->format('d/m/Y H:i') }}</span><span>Kerusakan fasilitas</span></div></div>
            <div class="d-flex gap-2 align-items-start">
                @if($r->damageDetail?->priority)
                    <span class="badge @switch($r->damageDetail->priority)
                        @case('rendah') text-bg-secondary @break
                        @case('sedang') text-bg-warning @break
                        @case('tinggi') text-bg-danger @break
                        @case('darurat') text-bg-danger @break
                    @endswitch">{{ ucfirst($r->damageDetail->priority) }}</span>
                @endif
                <span class="status-pill status-{{ $r->status }}">{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</span>
            </div>
        </div>
        @if(in_array($r->status, $processable, true))
            @php
                $errorsForThisForm = old('report_id') == $r->id;
                $detail = $r->damageDetail;
                if ($errorsForThisForm) {
                    $scheduledRepairAt = old('scheduled_repair_at');
                    if ($scheduledRepairAt) {
                        try {
                            $scheduledRepairAt = \Illuminate\Support\Carbon::parse($scheduledRepairAt)->format('Y-m-d\TH:i');
                        } catch (\Exception $e) {
                            // keep raw value if parsing fails
                        }
                    }
                    $priorityValue = old('priority');
                } else {
                    $scheduledRepairAt = $detail?->scheduled_repair_at?->format('Y-m-d\TH:i') ?? '';
                    $priorityValue = $detail?->priority ?? 'sedang';
                }
            @endphp
            <div class="accordion" id="accordion-sarpras-{{ $r->id }}">
                <!-- Process Tab -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#process-{{ $r->id }}" aria-expanded="true" aria-controls="process-{{ $r->id }}">
                            Kelola Perbaikan
                        </button>
                    </h2>
                    <div id="process-{{ $r->id }}" class="accordion-collapse collapse show" data-bs-parent="#accordion-sarpras-{{ $r->id }}">
                        <div class="accordion-body">
                            <form method="POST" enctype="multipart/form-data" action="{{ route('sarpras.process',$r) }}" class="row g-3">@csrf
                                <input type="hidden" name="report_id" value="{{ $r->id }}">
                                @if($errorsForThisForm && $errors->has('report'))
                                    <div class="col-12">
                                        <div class="invalid-feedback d-block">{{ $errors->first('report') }}</div>
                                    </div>
                                @endif
                                <div class="col-12 col-md-6">
                                    <label class="form-label required" for="priority_{{ $r->id }}">Prioritas</label>
                                    <select id="priority_{{ $r->id }}" name="priority" class="form-select @if($errorsForThisForm && $errors->has('priority')) is-invalid @endif" required>
                                        <option value="rendah" @selected($priorityValue === 'rendah')>Rendah</option>
                                        <option value="sedang" @selected($priorityValue === 'sedang')>Sedang</option>
                                        <option value="tinggi" @selected($priorityValue === 'tinggi')>Tinggi</option>
                                        <option value="darurat" @selected($priorityValue === 'darurat')>Darurat</option>
                                    </select>
                                    @if($errorsForThisForm && $errors->has('priority'))
                                        <div class="invalid-feedback">{{ $errors->first('priority') }}</div>
                                    @endif
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="scheduled_repair_at_{{ $r->id }}">Waktu Perbaikan</label>
                                    <input id="scheduled_repair_at_{{ $r->id }}" type="datetime-local" name="scheduled_repair_at" min="{{ $minSchedule }}" class="form-control @if($errorsForThisForm && $errors->has('scheduled_repair_at')) is-invalid @endif" value="{{ $scheduledRepairAt }}">
                                    @if($errorsForThisForm && $errors->has('scheduled_repair_at'))
                                        <div class="invalid-feedback">{{ $errors->first('scheduled_repair_at') }}</div>
                                    @endif
                                </div>
                                <div class="col-12 col-md-6" data-file-field>
                                    <label class="form-label" for="repair_photo_{{ $r->id }}">Foto setelah diperbaiki</label>
                                    <input id="repair_photo_{{ $r->id }}" type="file" name="repair_photo" class="form-control @if($errorsForThisForm && $errors->has('repair_photo')) is-invalid @endif" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-file-input>
                                    <small class="text-muted d-block mt-2">Format: JPG, PNG, atau WEBP. Ukuran maksimal: 5MB.</small>

                                    {{-- Preview container: dicari relatif dari [data-file-field], bukan lewat id --}}
                                    <div class="mt-3" style="display: none;" data-preview-container>
                                        <div class="d-flex align-items-start gap-3">
                                            <img src="" alt="Pratinjau foto" data-preview-image
                                                 style="max-width: 100px; max-height: 100px; border: 1px solid #ddd; padding: 4px; border-radius: 4px;">
                                            <div>
                                                <small class="text-muted d-block" data-preview-filename></small>
                                                <small class="text-muted d-block mb-2" data-preview-filesize></small>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFileField(this)">
                                                    Hapus
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    @if($errorsForThisForm && $errors->has('repair_photo'))
                                        <div class="invalid-feedback d-block mt-2">{{ $errors->first('repair_photo') }}</div>
                                    @endif
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label" for="note_{{ $r->id }}">Catatan</label>
                                    <input id="note_{{ $r->id }}" name="note" class="form-control @if($errorsForThisForm && $errors->has('note')) is-invalid @endif" placeholder="Opsional" maxlength="2000" value="{{ $errorsForThisForm ? old('note') : '' }}">
                                    @if($errorsForThisForm && $errors->has('note'))
                                        <div class="invalid-feedback">{{ $errors->first('note') }}</div>
                                    @endif
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-laporin" aria-label="Proses laporan kerusakan #{{ $r->report_number }}">Simpan Perbaikan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Reject Tab -->
                <div class="accordion-item">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#reject-{{ $r->id }}" aria-expanded="false" aria-controls="reject-{{ $r->id }}">
                            Tolak Laporan
                        </button>
                    </h2>
                    <div id="reject-{{ $r->id }}" class="accordion-collapse collapse" data-bs-parent="#accordion-sarpras-{{ $r->id }}">
                        <div class="accordion-body">
                            <form method="POST" action="{{ route('sarpras.reject', $r) }}" class="row g-3" onsubmit="return confirm('Tolak laporan kerusakan ini? Alur laporan akan berhenti.')">
                                @csrf
                                <div class="col-12"><label class="form-label required" for="reject_reason_{{ $r->id }}">Alasan penolakan</label><textarea id="reject_reason_{{ $r->id }}" name="reason" class="form-control" required maxlength="2000" placeholder="Jelaskan mengapa laporan tidak dapat diproses" rows="3"></textarea></div>
                                <div class="col-12"><button type="submit" class="btn btn-outline-danger" aria-label="Tolak laporan kerusakan #{{ $r->report_number }}">Tolak Laporan</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="status-note mb-0">
                Status laporan saat ini: <strong>{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</strong>.
                Tidak ada aksi Sarpras yang perlu dilakukan di tahap ini.
            </div>
        @endif
    </article>
@empty
    <div class="laporin-card text-center py-5 text-muted">Belum ada data.</div>
@endforelse
</div>
<div class="mt-3">{{ $reports->appends(request()->query())->links() }}</div>
@endsection

@push('scripts')
<script>
/**
 * File Upload Validation & Preview
 * Handles file type, size validation, and preview display.
 * Semua elemen dicari relatif terhadap wadah [data-file-field] agar id tidak pernah meleset.
 */
const FILE_VALID_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
const FILE_MAX_SIZE = 5 * 1024 * 1024; // 5MB

function getFileField(el) {
    return el.closest('[data-file-field]');
}

function setupFileInput(input) {
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) {
            clearFilePreview(input);
            return;
        }

        // Validate file type
        if (!FILE_VALID_TYPES.includes(file.type)) {
            showFileError(input, 'Format tidak didukung. Gunakan JPG, PNG, atau WEBP.');
            input.value = '';
            return;
        }

        // Validate file size
        if (file.size > FILE_MAX_SIZE) {
            const sizeMB = Math.round(file.size / 1024 / 1024);
            showFileError(input, `File terlalu besar (${sizeMB}MB). Maks 5MB.`);
            input.value = '';
            return;
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(event) {
            showFilePreview(input, event.target.result, file.name, file.size);
        };
        reader.readAsDataURL(file);
    });
}

function showFilePreview(input, imageSrc, fileName, fileSize) {
    const field = getFileField(input);
    const previewContainer = field?.querySelector('[data-preview-container]');
    const previewImage = field?.querySelector('[data-preview-image]');
    const filenameEl = field?.querySelector('[data-preview-filename]');
    const filesizeEl = field?.querySelector('[data-preview-filesize]');

    if (!previewContainer || !previewImage || !filenameEl || !filesizeEl) {
        console.error('Elemen pratinjau foto tidak lengkap untuk input', input.id);
        return;
    }

    previewImage.src = imageSrc;

    // Truncate filename if too long
    const displayName = fileName.length > 30 ? fileName.substring(0, 27) + '...' : fileName;
    filenameEl.textContent = `File: ${displayName}`;

    filesizeEl.textContent = `Ukuran: ${(fileSize / 1024).toFixed(1)}KB`;

    previewContainer.style.display = 'block';

    // Clear any error messages
    removeFileError(input);
}

function showFileError(input, message) {
    const field = getFileField(input) ?? input.parentElement;
    const previewContainer = field.querySelector('[data-preview-container]');

    // Hide preview
    if (previewContainer) previewContainer.style.display = 'none';

    // Remove existing error message
    removeFileError(input);

    // Show error message
    input.classList.add('is-invalid');

    // Add new error message
    const errorEl = document.createElement('div');
    errorEl.className = 'invalid-feedback d-block mt-2';
    errorEl.textContent = message;
    field.appendChild(errorEl);
}

function removeFileError(input) {
    const field = getFileField(input) ?? input.parentElement;

    input.classList.remove('is-invalid');
    field.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
}

function clearFilePreview(input) {
    const field = getFileField(input);
    const previewContainer = field?.querySelector('[data-preview-container]');

    input.value = '';
    removeFileError(input);

    if (previewContainer) {
        previewContainer.style.display = 'none';
    }
}

// Dipanggil tombol "Hapus": cari input file dari wadah tombol itu sendiri
function clearFileField(button) {
    const input = getFileField(button)?.querySelector('[data-file-input]');
    if (input) clearFilePreview(input);
}

// Initialize all file inputs on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-file-input]').forEach(input => {
        setupFileInput(input);
    });
});
</script>
@endpush
