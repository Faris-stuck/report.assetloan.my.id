@extends('layouts.app')
@section('title','Dasbor - LAPORIN')
@section('content')
@php
    $user = auth()->user();
    $roleLabel = ucwords(str_replace('_',' ', $user->role));
    $statusLabels = [
        'menunggu_verifikasi' => 'Menunggu Verifikasi',
        'memerlukan_informasi' => 'Perlu Informasi Tambahan',
        'dibuka_kembali' => 'Dibuka Kembali',
        'diverifikasi' => 'Sudah Diverifikasi',
        'ditugaskan' => 'Ditugaskan ke Petugas',
        'sedang_ditangani' => 'Sedang Ditangani',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi Pelapor',
        'selesai' => 'Selesai',
        'ditolak' => 'Ditolak',
        'diarsipkan' => 'Diarsipkan',
    ];

    // Kunci $stats dari DashboardController (total, violation, damage, pending,
    // done) TIDAK ada di $statusLabels, jadi kartu statistik selalu jatuh ke
    // fallback ucfirst() dan menampilkan "Violation", "Damage", "Pending",
    // "Done" dalam bahasa Inggris di dasbor berbahasa Indonesia. Label khusus
    // ini juga menjelaskan cakupan angkanya: "pending" hanya menghitung status
    // menunggu_verifikasi, bukan semua laporan yang belum selesai.
    $statLabels = [
        'total' => 'Total Laporan',
        'violation' => 'Laporan Pelanggaran',
        'damage' => 'Laporan Kerusakan',
        'pending' => 'Menunggu Verifikasi',
        'done' => 'Selesai',
    ];

    $quickMenus = [
        ['label' => 'Kesiswaan', 'desc' => 'Tangani pelanggaran siswa', 'icon' => '⚑', 'route' => route('kesiswaan.index'), 'allowed' => $user->canAccessMenuFor('kesiswaan')],
        ['label' => 'Sarpras', 'desc' => 'Tangani kerusakan fasilitas', 'icon' => '⚒', 'route' => route('sarpras.index'), 'allowed' => $user->canAccessMenuFor('sarpras')],
        ['label' => 'Akun Pengguna', 'desc' => 'Kelola akun petugas', 'icon' => '👥', 'route' => route('admin.users.index'), 'allowed' => $user->isSuperadmin()],
        ['label' => 'Kode QR', 'desc' => 'Buat dan unduh QR', 'icon' => '▦', 'route' => route('admin.qrcodes.index'), 'allowed' => $user->isSuperadmin()],
        ['label' => 'Riwayat Aktivitas', 'desc' => 'Lihat catatan sistem', 'icon' => '☰', 'route' => route('admin.audit'), 'allowed' => $user->isSuperadmin()],
    ];
    $flowSteps = match ($user->role) {
        'kesiswaan' => ['Laporan Masuk','Kesiswaan Memverifikasi','Kesiswaan Menangani','Pelapor Konfirmasi','Selesai'],
        'sarpras' => ['Laporan Masuk','Sarpras Memverifikasi','Sarpras Menangani','Bukti Penyelesaian','Selesai'],
        'wali_kelas' => ['Laporan di Kelas Ampuan','Wali Kelas Membaca','Pemantauan Tanpa Mengubah Laporan'],
        default => ['Laporan Masuk','Data Diperiksa','Petugas Menangani','Pelapor Konfirmasi','Selesai'],
    };
@endphp
<div class="page-header">
    <div>
        <span class="page-kicker">Dasbor laporan</span>
        <h1 class="page-title h2 mt-2">Ringkasan LAPORIN</h1>
        <p class="page-subtitle">Menu dan laporan di bawah mengikuti peran akun <strong>{{ $roleLabel }}</strong>. Jika URL diketik manual, akses tetap dibatasi oleh sistem.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    @foreach($stats as $k=>$v)
        <div class="col-6 col-lg">
            <div class="laporin-card stat-card h-100">
                <div class="stat-label">{{ $statLabels[$k] ?? str_replace('_',' ', ucfirst($k)) }}</div>
                <div class="stat-value">{{ $v }}</div>
            </div>
        </div>
    @endforeach
</div>

<section class="laporin-card role-chart-card mb-4" aria-labelledby="role-chart-title" data-chart-counts='@json($chart['counts'])'>
    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h2 id="role-chart-title" class="h5 fw-bold mb-1">{{ $chart['title'] }}</h2>
            <p class="small-muted mb-0">Jumlah laporan masuk per bulan, otomatis disaring sesuai hak akses {{ $roleLabel }}.</p>
        </div>
        <span class="badge text-bg-light rounded-pill">Diagram batang</span>
    </div>
    <div class="role-bar-chart" role="img" aria-label="{{ $chart['title'] }}: @foreach($chart['labels'] as $index => $label){{ $label }} {{ $chart['counts'][$index] }} laporan{{ $loop->last ? '' : ', ' }}@endforeach">
        @foreach($chart['labels'] as $index => $label)
            @php($height = $chart['counts'][$index] > 0 ? max(8, (int) round(($chart['counts'][$index] / $chart['max']) * 100)) : 0)
            <div class="role-bar-item">
                <span class="role-bar-value">{{ $chart['counts'][$index] }}</span>
                <div class="role-bar-track" aria-hidden="true">
                    <span class="role-bar-fill" style="height: {{ $height }}%"></span>
                </div>
                <span class="role-bar-label">{{ $label }}</span>
            </div>
        @endforeach
    </div>
</section>

<div class="row g-4 mb-4 dashboard-focus-row">
    <div class="col-lg-7">
        <div class="laporin-card dashboard-focus-card h-100">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                <div>
                    <h2 class="h5 fw-bold mb-1">Menu Cepat Sesuai Role</h2>
                    <p class="small-muted mb-0">Hanya menu yang boleh diakses akun ini yang ditampilkan.</p>
                </div>
                <span class="badge text-bg-success rounded-pill">{{ $roleLabel }}</span>
            </div>
            <div class="menu-grid">
                @foreach($quickMenus as $menu)
                    @if($menu['allowed'])
                        <a class="menu-tile" href="{{ $menu['route'] }}">
                            <span class="menu-icon">{{ $menu['icon'] }}</span>
                            <span><strong>{{ $menu['label'] }}</strong><small class="d-block small-muted">{{ $menu['desc'] }}</small></span>
                        </a>
                    @endif
                @endforeach
                @if($user->isSuperadmin())
                    @foreach(['classes'=>'Kelas','subjects'=>'Mata Pelajaran','staff-units'=>'Unit Staf','violation-types'=>'Jenis Pelanggaran','damage-categories'=>'Kategori Kerusakan','students'=>'Siswa'] as $resource=>$label)
                        <a class="menu-tile" href="{{ route('admin.master.index',$resource) }}">
                            <span class="menu-icon">•</span>
                            <span><strong>{{ $label }}</strong><small class="d-block small-muted">Data pilihan form</small></span>
                        </a>
                    @endforeach
                @endif
                @if($user->isRole('wali_kelas'))
                    <div class="status-note mb-0">Mode baca saja: laporan yang tampil hanya perundungan atau pelanggaran pada kelas yang Anda ampu.</div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="laporin-card dashboard-focus-card h-100">
            <h2 class="h5 fw-bold mb-3">Flow Validasi Laporan</h2>
            <p class="small-muted">Alur umum dari laporan masuk sampai selesai.</p>
            <div class="flowchart compact">
                @foreach($flowSteps as $step)
                    <div class="flow-node">{{ $step }}</div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="laporin-card">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h5 fw-bold mb-1">Daftar Laporan</h2>
            <p class="small-muted mb-0">Laporan yang terlihat sudah disaring sesuai hak akses akun.</p>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>No</th><th>Judul</th><th>Jenis</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
            <tbody>
            @forelse($reports as $r)
                <tr>
                    <td><code>{{ $r->report_number }}</code></td>
                    <td><strong>{{ $r->title }}</strong><div class="small-muted">{{ $r->created_at->format('d/m/Y H:i') }}</div></td>
                    <td>{{ $r->report_type === 'violation' ? 'Pelanggaran' : 'Kerusakan' }}</td>
                    <td><span class="status-pill status-{{ $r->status }}">{{ $statusLabels[$r->status] ?? str_replace('_',' ',$r->status) }}</span></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-laporin" href="{{ route('reports.show',$r) }}">Detail</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">Belum ada laporan.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $reports->links() }}</div>
</div>
@endsection
