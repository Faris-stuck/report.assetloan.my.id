<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perubahan Status Laporan - {{ $reportNumber }}</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; color: #333; }
        .container { background: #ffffff; border-radius: 8px; padding: 30px; max-width: 600px; margin: 0 auto; }
        .header { background: #5C3D9E; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 20px; }
        .status-badge { display: inline-block; background: #5C3D9E; color: white; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: bold; }
        .info-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .info-table td { padding: 8px 0; border-bottom: 1px solid #eee; }
        .info-table td:first-child { color: #666; width: 40%; }
        .info-table td:last-child { font-weight: 600; }
        .note-box { background: #f0f0ff; border-left: 4px solid #5C3D9E; padding: 10px 15px; border-radius: 4px; margin: 10px 0; font-size: 14px; }
        .footer { text-align: center; padding: 15px; font-size: 12px; color: #888; border-top: 1px solid #eee; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>LAPORIN</h1>
            <p style="margin:5px 0 0;font-size:14px;">SMK Taruna Bangsa Bekasi</p>
        </div>
        <div class="content">
            <p>Yth. <strong>{{ $report->reporter_name }}</strong>,</p>
            <p>Status laporan Anda telah diperbarui:</p>

            <div style="text-align:center;margin:20px 0;">
                <span class="status-badge">{{ $statusLabel }}</span>
            </div>

            <table class="info-table">
                <tr>
                    <td>Nomor Laporan</td>
                    <td>{{ $reportNumber }}</td>
                </tr>
                <tr>
                    <td>Jenis Laporan</td>
                    <td>{{ $reportTypeLabel }}</td>
                </tr>
                <tr>
                    <td>Judul</td>
                    <td>{{ $report->title }}</td>
                </tr>
                <tr>
                    <td>Waktu Update</td>
                    <td>{{ $report->updated_at->format('d/m/Y H:i') }} WIB</td>
                </tr>
            </table>

            @if($catatan)
            <div class="note-box">
                <strong>Catatan:</strong><br>
                {{ $catatan }}
            </div>
            @endif

            <p style="font-size:13px;color:#666;margin-top:15px;">
                Untuk melihat detail lengkap dan riwayat status, gunakan fitur Lacak Status dengan nomor laporan dan kode akses Anda.
            </p>
        </div>
        <div class="footer">
            LAPORIN SMK Taruna Bangsa Bekasi — Kanal Pelaporan Perundungan & Kerusakan<br>
            Email ini dikirim secara otomatis. Jangan membalas email ini.
        </div>
    </div>
</body>
</html>
