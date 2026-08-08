<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Laporan Diterima - {{ $reportNumber }}</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 20px;
            color: #333333;
        }

        .container {
            background: #ffffff;
            border-radius: 8px;
            max-width: 600px;
            margin: 0 auto;
            overflow: hidden;
        }

        .header {
            background: #04783e;
            color: #ffffff;
            padding: 24px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 24px;
        }

        .header p {
            margin: 6px 0 0;
            font-size: 14px;
        }

        .content {
            padding: 28px;
        }

        .credential-grid {
            width: 100%;
            margin: 20px 0;
        }

        .credential-box {
            background: #f4fff8;
            border: 2px dashed #00a651;
            border-radius: 8px;
            padding: 16px;
            margin: 14px 0;
            text-align: center;
        }

        .credential-label {
            font-size: 12px;
            color: #647067;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .credential-value {
            font-size: 22px;
            font-weight: bold;
            color: #064225;
            font-family: monospace;
            letter-spacing: 2px;
            word-break: break-word;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0;
        }

        .info-table td {
            padding: 9px 0;
            border-bottom: 1px solid #eeeeee;
            vertical-align: top;
        }

        .info-table td:first-child {
            color: #666666;
            width: 40%;
        }

        .info-table td:last-child {
            font-weight: 600;
        }

        .warning {
            background: #fff8e1;
            border-left: 4px solid #ffc107;
            padding: 12px 15px;
            border-radius: 4px;
            font-size: 14px;
            line-height: 1.5;
        }

        .tracking-info {
            background: #f4fff8;
            border: 1px solid #dfeee5;
            border-radius: 8px;
            padding: 14px;
            margin-top: 18px;
            font-size: 14px;
            line-height: 1.6;
        }

        .footer {
            text-align: center;
            padding: 18px 24px;
            font-size: 12px;
            color: #888888;
            border-top: 1px solid #eeeeee;
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>LAPORIN</h1>
        <p>SMK Taruna Bangsa Bekasi</p>
    </div>

    <div class="content">

        <p>
            Yth.
            <strong>{{ $report->reporter_name }}</strong>,
        </p>

        <p>
            Laporan Anda telah
            <strong>berhasil diterima</strong>
            oleh sistem LAPORIN.
        </p>

        <p>
            Simpan <strong>Nomor Laporan</strong> dan
            <strong>Kode Akses</strong> berikut karena keduanya diperlukan
            untuk melihat perkembangan laporan.
        </p>


        {{-- ====================================================== --}}
        {{-- NOMOR LAPORAN                                         --}}
        {{-- ====================================================== --}}

        <div class="credential-box">

            <div class="credential-label">
                Nomor Laporan
            </div>

            <div class="credential-value">
                {{ $reportNumber }}
            </div>

        </div>


        {{-- ====================================================== --}}
        {{-- KODE AKSES                                             --}}
        {{-- ====================================================== --}}

        <div class="credential-box">

            <div class="credential-label">
                Kode Akses
            </div>

            <div class="credential-value">
                {{ $accessCode }}
            </div>

        </div>


        {{-- ====================================================== --}}
        {{-- INFORMASI LAPORAN                                      --}}
        {{-- ====================================================== --}}

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
                <td>
                    {{ $report->created_at?->format('d/m/Y H:i') ?? '-' }} WIB
                </td>
            </tr>

        </table>


        {{-- ====================================================== --}}
        {{-- PERINGATAN                                             --}}
        {{-- ====================================================== --}}

        <div class="warning">

            <strong>⚠️ Simpan informasi ini dengan aman.</strong>

            <br>

            Nomor laporan dan kode akses digunakan untuk membuka
            halaman pelacakan laporan Anda.

            <br><br>

            Jangan membagikan kode akses kepada orang yang tidak berkepentingan.

        </div>


        {{-- ====================================================== --}}
        {{-- CARA MELACAK                                           --}}
        {{-- ====================================================== --}}

        <div class="tracking-info">

            <strong>Cara melacak laporan:</strong>

            <br>

            1. Buka halaman <strong>Lacak Laporan</strong> di LAPORIN.

            <br>

            2. Masukkan Nomor Laporan.

            <br>

            3. Masukkan Kode Akses.

            <br>

            4. Tekan tombol <strong>Lacak Laporan</strong>.

        </div>

    </div>


    <div class="footer">

        LAPORIN SMK Taruna Bangsa Bekasi

        <br>

        Kanal Pelaporan Perundungan, Pelanggaran, dan Kerusakan Fasilitas

        <br><br>

        Email ini dikirim secara otomatis. Jangan membalas email ini.

    </div>

</div>

</body>
</html>