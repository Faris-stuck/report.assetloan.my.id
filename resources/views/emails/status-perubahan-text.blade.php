LAPORIN — Notifikasi Laporan

Yth. {{ $report->reporter_name }},

{{ $event === 'created' ? 'Laporan Anda telah berhasil diterima oleh sistem LAPORIN.' : 'Status laporan Anda telah diperbarui.' }}

Nomor Laporan: {{ $report->report_number }}
Judul: {{ $report->title }}
Status: {{ $statusLabel }}
@if($accessCode)
Kode Akses: {{ $accessCode }}
@endif

Pantau laporan Anda melalui:
{{ url('/lacak') }}

Pesan ini dikirim otomatis oleh LAPORIN. Mohon tidak membalas email ini.

LAPORIN SMK Taruna Bangsa Bekasi
