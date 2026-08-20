<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>LAPORIN SMK Taruna Bangsa Bekasi</title>
        <style>
            :root { color-scheme: light; }
            * { box-sizing: border-box; }
            body { margin: 0; min-height: 100vh; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 100%); color: #0f172a; }
            body::before { content: ""; position: fixed; inset: 0; background: radial-gradient(circle at top left, rgba(16, 185, 129, 0.16), transparent 32%), radial-gradient(circle at bottom right, rgba(14, 165, 233, 0.12), transparent 28%); pointer-events: none; }
            .page-shell { min-height: 100vh; display: grid; place-items: center; padding: 2rem; }
            .card { position: relative; width: min(100%, 900px); padding: 3rem; border-radius: 2rem; background: rgba(255,255,255,0.96); box-shadow: 0 40px 120px rgba(15, 23, 42, 0.12); overflow: hidden; }
            .brand { display: inline-flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; font-weight: 700; letter-spacing: 0.24em; text-transform: uppercase; color: #047857; }
            .brand-mark { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 9999px; background: #047857; color: white; font-size: 1rem; }
            h1 { margin: 0; font-size: clamp(2.25rem, 3vw, 3.75rem); line-height: 1; letter-spacing: -0.04em; }
            p { max-width: 54rem; margin: 1.5rem 0 2rem; font-size: 1rem; line-height: 1.8; color: #475569; }
            .actions { display: flex; flex-wrap: wrap; gap: 1rem; }
            .button { display: inline-flex; align-items: center; justify-content: center; min-width: 10rem; padding: 0.95rem 1.5rem; border-radius: 999px; text-decoration: none; font-weight: 700; transition: transform .2s ease, box-shadow .2s ease; }
            .button-primary { background: #047857; color: white; box-shadow: 0 18px 40px rgba(4, 120, 87, 0.18); }
            .button-primary:hover { transform: translateY(-1px); }
            .button-secondary { background: white; border: 1px solid #cbd5e1; color: #0f172a; }
            .button-secondary:hover { box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08); }
            .note { margin-top: 2rem; font-size: 0.95rem; color: #64748b; }
        </style>
    </head>
    <body>
        <main class="page-shell">
            <article class="card">
                <div class="brand"><img src="{{ asset('images/branding/logo tb.png') }}" alt="Logo SMK Taruna Bangsa" style="width:2.25rem;height:2.25rem;border-radius:9999px;background:#fff;padding:.2rem;object-fit:contain;">LAPORIN</div>
                <h1>Selamat datang di LAPORIN</h1>
                <p>LAPORIN adalah kanal pelaporan perundungan dan kerusakan fasilitas untuk warga SMK Taruna Bangsa Bekasi. Pelapor publik dapat mengirim laporan tanpa login, sementara pengelola dapat masuk untuk memantau dan menindaklanjuti laporan.</p>
                <div class="actions">
                    <a href="{{ route('public.report') }}" class="button button-primary">Buat Laporan</a>
                </div>
                <p class="note">Jika belum terdaftar sebagai pengelola, silakan hubungi admin sekolah untuk mendapatkan akses masuk.</p>
            </article>
        </main>
    </body>
</html>
