<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Diterima - {{ $reportNumber }}</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px; color: #333; }
        .container { background: #ffffff; border-radius: 8px; padding: 30px; max-width: 600px; margin: 0 auto; }
        .header { background: #5C3D9E; color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 20px; }
        .credential-box { background: #f8f4ff; border: 2px dashed #5C3D9E; border-radius: 8px; padding: 15px; margin: 15px 0; text-align: center; }
        .credential-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px; }
        .credential-value { font-size: 22px; font-weight: bold; color: #5C3D9E; font-family: monospace; letter-spacing: 2px; }
        .info-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .info-table td { padding: 8px 0; border-bottom: 1px solid #eee; }
        .info-table td:first-child { color: #666; width: 40%; }
        .info-table td:last-child { font-weight: 600; }
        .warning { background: #fff8e1; border-left: 4px solid #ffc107; padding: 10px 15px; border-radius: 4px; font-size: 14px; color: #333; }
        .footer { text-align: center; padding: 15px; font-size: 12px; color: #888; border-top: 1px solid #eee; margin-top: 20px; }
        .btn { display: inline-block; background: #5C3D9E; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; margin: 10px 5px; }
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
            <p>Laporan Anda telah <strong>berhasil diterima</strong> oleh sistem LAPORIN. Berikut detailnya:</p>

            <div class="credential-box">
                <div class="credential-label">Nomor Laporan</div>
                <div class="credential-value">{{ $reportNumber }}</div>
            </div>

            <table class="info-table">
                <tr>
                    <td>Jenis Laporan</td>
                    <td>{{ $reportTypeLabel }}</td>
                </tr>
                <tr>
                    <td>Judul</td>
                    <td>{{ $report->title }}</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td>{{ $statusLabel }}</td>
                </tr>
                <tr>
                    <td>Waktu Kirim</td>
                    <td>{{ $report->created_at->format('d/m/Y H:i') }} WIB</td>
                </tr>
            </table>

            <div class="warning">
                <strong>⚠️ Simpan informasi ini.</strong><br>
                Nomor laporan dan kode akses diperlukan untuk melacak status laporan Anda. Kode akses hanya dikirim melalui email ini.
            </div>

            <p style="font-size:13px;color:#666;margin-top:15px;">
                <strong>Cara melacak:</strong> Kunjungi halaman Lacak Laporan LAPORIN, masukkan nomor laporan dan kode akses di atas.
            </p>
        </div>
        <div class="footer">
            LAPORIN SMK Taruna Bangsa Bekasi — Kanal Pelaporan Perundungan & Kerusakan<br>
            Email ini dikirim secara otomatis. Jangan membalas email ini.
        </div>
    </div>
</body>
</html>
