<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perubahan Status Laporan - {{ $report->report_number }}</title>
</head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;">
    <h2>LAPORIN - SMK Taruna Bangsa Bekasi</h2>
    <p>Yth. {{ $report->reporter_name }},</p>
    <p>Status laporan Anda telah diperbarui.</p>
    <p><strong>Nomor Laporan:</strong> {{ $report->report_number }}</p>
    <p><strong>Status:</strong> {{ $statusLabel }}</p>
    <p><strong>Judul:</strong> {{ $report->title }}</p>
    <p>Silakan gunakan fitur Lacak Status di website LAPORIN untuk melihat detail dan riwayat laporan.</p>
    <p>Pesan ini dikirim otomatis. Mohon tidak membalas email ini.</p>
</body>
</html>
