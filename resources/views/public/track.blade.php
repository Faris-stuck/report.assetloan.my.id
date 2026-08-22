@extends('layouts.app')
@section('title','Lacak Laporan LAPORIN')
@section('meta_title','Lacak Laporan SMK Taruna Bangsa Bekasi | Cek Status LAPORIN')
@section('meta_description','Lacak status laporan LAPORIN SMK Taruna Bangsa Bekasi memakai nomor laporan dan kode akses 6 digit. Pantau tindak lanjut laporan siswa dan fasilitas sekolah.')
@section('canonical'){{ route('track.form') }}@endsection
@section('content')
<div class="tracking-shell">
    <section class="tracking-overview" aria-labelledby="tracking-title">
        <span class="page-kicker">Pelacakan aman</span>
        <h1 id="tracking-title" class="page-title h2 mt-2">Lacak status laporan</h1>
        <p class="page-subtitle">Masukkan nomor laporan dan kode akses 6 digit yang muncul setelah laporan terkirim. Data pelacakan dibatasi agar privasi tetap terjaga.</p>
        <div class="laporin-card card-soft mt-4">
            <div class="flowchart compact">
                <div class="flow-node">Masuk</div>
                <div class="flow-node">Diproses</div>
                <div class="flow-node">Konfirmasi</div>
                <div class="flow-node">Selesai</div>
            </div>
        </div>
    </section>
    <section class="tracking-form-panel" aria-labelledby="tracking-form-title">
        <div class="laporin-card p-4 p-lg-5">
            <h2 id="tracking-form-title" class="h4 fw-bold mb-1">Formulir Pelacakan</h2>
            <p class="small-muted mb-4">Salin dan tempel nomor laporan serta kode akses dari halaman laporan terkirim.</p>
            {{-- Pembatasan perangkat diberitahukan DI DEPAN, bukan setelah gagal.
                 Pesan penolakannya sengaja tidak menyebut penyebab pastinya
                 (supaya kode akses 6 digit tidak bisa ditebak dari perbedaan
                 pesan), jadi pelapor yang mencoba dari ponsel lain hanya melihat
                 "tidak cocok" dan menyimpulkan laporannya hilang. --}}
            <div class="alert alert-info py-2 px-3 small mb-4" role="note">
                Laporan hanya bisa dilacak dari <strong>perangkat dan peramban yang sama</strong> seperti saat laporan dikirim. Jika Anda mengirim laporan dari ponsel, lacak juga dari ponsel itu.
                {{-- Jalan keluar harus ada di teks statis seperti ini, bukan di
                     pesan kesalahan. Pesan penolakan pencarian sengaja tidak
                     membedakan nomor salah, kode salah, dan perangkat berbeda,
                     jadi ia tidak boleh menyarankan "datang ke Kesiswaan" —
                     saran itu keliru bagi orang yang cuma salah ketik. Padahal
                     pelapor yang menghapus data peramban SELALU berhenti di
                     pencarian, bukan di aksi, sehingga tanpa keterangan ini ia
                     tidak punya petunjuk apa pun dan menyimpulkan laporannya
                     hilang. --}}
                Kalau perangkat itu sudah tidak ada atau data peramban-nya sudah dihapus, laporan Anda <strong>tetap diproses</strong> — bawa nomor laporan Anda ke ruang Kesiswaan (laporan pelanggaran) atau Sarpras (laporan kerusakan) untuk menanyakan tindak lanjutnya.
            </div>
            <form method="POST" action="{{ route('track.search') }}" class="row g-3">
                @csrf
                <div class="col-12">
                    <label class="form-label required" for="report_number">Nomor Laporan</label>
                    {{-- maxlength longgar dengan sengaja. Nomor kanonisnya hanya
                         17 karakter, tapi maxlength memotong hasil PASTE sebelum
                         normalizer JS membuang label/spasi/tanda hubung, jadi
                         batas ketat justru merusak tempelan yang sebenarnya
                         benar. Panjang sebenarnya ditegakkan normalizer dan
                         aturan regex di server. --}}
                    <input id="report_number" name="report_number" value="{{ old('report_number') }}" class="form-control" placeholder="LAP-ABC234-XYZ789" required autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="next" maxlength="64" aria-describedby="report-number-help" data-normalize-report-number>
                    <small id="report-number-help" class="text-muted">Contoh yang dapat langsung ditempel: <strong>LAP-ABC234-XYZ789</strong>. Spasi atau tanda hubung dari hasil salin-tempel akan dihapus otomatis.</small>
                </div>
                <div class="col-12">
                    <label class="form-label required" for="access_code">Kode Akses</label>
                    {{-- Dulu maxlength="16": menempel "Kode Akses: 123456" (18
                         karakter) dipotong jadi "Kode Akses: 1234", normalizer
                         menyisakan "1234", dan server menolak dengan "kode akses
                         harus 6 digit" — padahal kode yang disalin pelapor sudah
                         benar. Pelapor hanya menerima kode ini sekali dan tidak
                         punya cara menebak bahwa halamannya sendiri yang merusak
                         tempelannya. Enam digit tetap dijaga di normalizer. --}}
                    <input id="access_code" name="access_code" value="{{ old('access_code') }}" class="form-control" inputmode="numeric" required autocomplete="one-time-code" enterkeyhint="search" placeholder="Contoh: 123456" maxlength="32" aria-describedby="access-code-help" data-normalize-access-code>
                    <small id="access-code-help" class="text-muted">Masukkan 6 angka. Spasi, tanda hubung, atau tulisan lain dari hasil salin-tempel akan dihapus otomatis.</small>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-laporin w-100">Lacak Laporan</button>
                </div>
            </form>
        </div>
    </section>
    <section class="laporin-card p-4 p-lg-5 mt-4 seo-prose" aria-labelledby="tracking-help-title">
        <h2 id="tracking-help-title">Cara melacak laporan LAPORIN</h2>
        <p>Masukkan nomor laporan dan kode akses yang Anda terima setelah laporan dikirim. Nomor laporan membantu menemukan laporan, sedangkan kode akses digunakan untuk menjaga agar status hanya dapat dibuka oleh pihak yang memiliki akses.</p>
        <p>Untuk panduan membuat laporan, baca <a href="{{ route('seo.bullying-guide') }}">panduan lapor pembullyan dan perundungan</a>, <a href="{{ route('seo.student-violation') }}">panduan lapor pelanggaran siswa</a>, atau <a href="{{ route('seo.facility-damage') }}">panduan lapor kerusakan fasilitas sekolah</a>. Pertanyaan lain dijawab di <a href="{{ route('seo.faq') }}">FAQ LAPORIN</a>.</p>
    </section>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const reportNumber = document.querySelector('[data-normalize-report-number]');
    const accessCode = document.querySelector('[data-normalize-access-code]');

    const bindNormalizer = (input, normalize) => {
        if (!input) return;
        const apply = () => {
            const before = input.value;
            const next = normalize(before);
            if (next === before) return;

            // Karet dihitung ulang, tidak dibiarkan melompat ke akhir.
            // Sebelumnya setiap penekanan tombol menulis ulang value tanpa
            // memulihkan posisi karet, sehingga pelapor yang menyisipkan satu
            // angka yang terlewat di tengah nomor laporan mendapati kursornya
            // terlempar ke ujung dan angka berikutnya masuk di tempat salah.
            const caret = input.selectionStart ?? before.length;
            const kept = normalize(before.slice(0, caret)).length;
            input.value = next;
            const position = Math.max(0, Math.min(next.length, kept));
            if (typeof input.setSelectionRange === 'function') {
                input.setSelectionRange(position, position);
            }
        };
        input.addEventListener('input', apply);
        input.addEventListener('blur', apply);
    };

    // Batas panjang ditegakkan DI SINI, bukan lewat atribut maxlength.
    // maxlength memotong tempelan sebelum label/spasi/tanda hubung dibuang,
    // sehingga tempelan yang sebenarnya benar berubah jadi kode buntung dan
    // ditolak server tanpa petunjuk yang bisa dipahami pelapor.
    bindNormalizer(reportNumber, (value) => value.toUpperCase().replace(/[^A-Z0-9-]/g, '').slice(0, 24));
    bindNormalizer(accessCode, (value) => value.replace(/[^0-9]/g, '').slice(0, 6));
});
</script>
@endpush
