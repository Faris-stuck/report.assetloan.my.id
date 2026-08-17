<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAPORIN — Notifikasi {{ $report->report_number }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial,Helvetica,sans-serif;color:#222;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f7fa;">
<tr><td align="center" style="padding:24px 12px;">
<table width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;">
<tr><td style="padding:28px;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:24px;color:#222;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;"><tr><td style="vertical-align:middle;"><img src="{{ $message->embed(public_path('images/branding/logo tb.png')) }}" alt="Logo SMK Taruna Bangsa" width="52" height="52" style="display:block;width:52px;height:52px;object-fit:contain;background:#ffffff;border-radius:10px;padding:4px;"></td><td style="padding-left:12px;vertical-align:middle;"><p style="margin:0;font-size:20px;line-height:28px;font-weight:bold;">LAPORIN — Notifikasi Laporan</p></td></tr></table>
<p style="margin:0 0 16px;">Yth. {{ $report->reporter_name }},</p>
<p style="margin:0 0 16px;">{{ $event === 'created' ? 'Laporan Anda telah berhasil diterima oleh sistem LAPORIN.' : 'Status laporan Anda telah diperbarui.' }}</p>
<p style="margin:0 0 8px;"><strong>Nomor Laporan:</strong> {{ $report->report_number }}</p>
<p style="margin:0 0 8px;"><strong>Judul:</strong> {{ $report->title }}</p>
<p style="margin:0 0 16px;"><strong>Status:</strong> {{ $statusLabel }}</p>
@if($accessCode)
<p style="margin:0 0 16px;"><strong>Kode Akses:</strong> {{ $accessCode }}</p>
@endif
<p style="margin:0 0 16px;">Pantau laporan Anda melalui <a href="{{ url('/lacak') }}" style="color:#1155cc;">LAPORIN</a>.</p>
<p style="margin:0;font-size:14px;line-height:21px;color:#666;">Pesan ini dikirim otomatis oleh LAPORIN. Mohon tidak membalas email ini.</p>
</td></tr></table>
</td></tr></table>
</body>
</html>
