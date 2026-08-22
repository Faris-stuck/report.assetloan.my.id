@extends('layouts.app')
@section('title','Status Laporan')
@section('content')
@php
    $flow = [
        'menunggu_verifikasi' => 'Laporan Masuk',
        'memerlukan_informasi' => 'Butuh Info Tambahan',
        'dibuka_kembali' => 'Info Diterima',
        'sedang_ditangani' => 'Sedang Ditangani',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'selesai' => 'Selesai',
    ];
    $statusLabels = [
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'memerlukan_informasi' => 'Perlu Informasi Tambahan',
        'dibuka_kembali' => 'Dibuka Kembali',
        'sedang_ditangani' => 'Sedang Ditangani',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi Pelapor',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
    ];

    // Diagram alur hanya mengenal enam status di $flow. Status di luar itu
    // (ditolak, diverifikasi, ditugaskan, diarsipkan) membuat SEMUA simpul
    // tampil kelabu tanpa satu pun yang aktif, sehingga pelapor melihat
    // diagram mati dan menyimpulkan laporannya tidak diproses. Lebih jujur
    // menyembunyikan diagramnya dan mengandalkan lencana status di atas.
    $flowHasActiveStep = array_key_exists($report->status, $flow);
@endphp
<div class="laporin-card p-4 p-lg-5">
    <div class="page-header mb-4">
        <div>
            <span class="page-kicker">Status laporan</span>
            <h1 class="page-title h3 mt-2">{{ $report->report_number }}</h1>
            <p class="page-subtitle">{{ $report->title }}</p>
        </div>
        <span class="status-pill status-{{ $report->status }}">{{ $statusLabels[$report->status] ?? str_replace('_',' ', $report->status) }}</span>
    </div>
    @if($flowHasActiveStep)
        <div class="flowchart compact mb-4">
            @foreach($flow as $status=>$label)
                <div class="flow-node {{ $report->status === $status ? 'is-active' : '' }}">{{ $label }}</div>
            @endforeach
        </div>
    @endif
    @if($report->status === 'ditolak')
        {{-- Alasan penolakan sengaja disimpan sebagai catatan internal, jadi
             yang bisa diberikan ke pelapor adalah langkah berikutnya. Tanpa itu
             pesannya hanya memberi tahu alurnya mati tanpa jalan keluar. --}}
        <div class="alert alert-danger">
            <strong>Laporan ditolak.</strong> Alur pemrosesan laporan ini berhenti dan tidak akan dilanjutkan.
            Jika Anda yakin kejadiannya benar terjadi, buat <a href="{{ route('public.report') }}">laporan baru</a>
            dengan keterangan yang lebih rinci dan bukti pendukung seperti foto atau tangkapan layar.
        </div>
    @endif
    @if($report->status === 'menunggu_konfirmasi')
        <div class="alert alert-info">Petugas menyatakan laporan sudah ditangani. Jika kondisi di lapangan sudah benar, klik <strong>Konfirmasi Selesai</strong>. Jika belum sesuai, tambahkan informasi agar petugas menindaklanjuti kembali.</div>
    @endif
    {{-- Yang ditampilkan di sini adalah deskripsi yang DIKIRIM PELAPOR, bukan
         ringkasan dari petugas. Judul lama "Ringkasan untuk pelapor" membuat
         pelapor menyangka ini jawaban resmi sekolah atas laporannya. --}}
    <div class="detail-box mb-4"><h2 class="h6 fw-bold">Isi laporan yang Anda kirim</h2><p class="mb-0">{{ $report->description }}</p></div>
    <h2 class="h5 fw-bold">Riwayat Laporan</h2>
    <ul class="list-group mb-4">
        @forelse($report->histories as $h)
            <li class="list-group-item border-0 border-bottom px-0"><strong>{{ $statusLabels[$h->new_status] ?? str_replace('_',' ', $h->new_status) }}</strong><div class="small-muted">{{ $h->created_at->format('d/m/Y H:i') }}</div>@if($h->public_note)<div class="mt-1">{{ $h->public_note }}</div>@endif</li>
        @empty
            <li class="list-group-item border-0 px-0">Belum ada riwayat.</li>
        @endforelse
    </ul>
    @if(in_array($report->status, ['memerlukan_informasi','dibuka_kembali','menunggu_konfirmasi']))
        {{-- Textarea ini menampung sampai 3000 karakter, dan bukti pelacakan
             hangus setelah 15 menit. Tanpa nilai awal, pelapor yang menulis
             panjang lalu ditolak karena sesinya lewat kehilangan SELURUH
             tulisannya. $noteDraft diisi TrackingController dari draf yang
             disimpan tepat sebelum penolakan; old('note') menangani kasus
             validasi gagal biasa. --}}
        <form method="POST" action="{{ route('track.info', $report) }}" class="detail-box mb-3">@csrf<label class="form-label required" for="note">Tambahkan Informasi</label><textarea id="note" name="note" class="form-control mb-3" required maxlength="3000" rows="4" placeholder="Tulis informasi tambahan atau alasan jika laporan belum selesai.">{{ old('note', $noteDraft ?? '') }}</textarea><button type="submit" class="btn btn-outline-laporin">Kirim Informasi</button></form>
    @endif
    @if($report->status === 'menunggu_konfirmasi')
        <form method="POST" action="{{ route('track.confirm', $report) }}" onsubmit="return confirm('Konfirmasi bahwa laporan ini sudah selesai?')">@csrf<button type="submit" class="btn btn-laporin">Konfirmasi Selesai</button></form>
    @endif
</div>
@endsection
