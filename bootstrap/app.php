<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnterpriseSecurity;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '')))));
        if ($trustedProxies !== []) {
            $middleware->trustProxies(at: $trustedProxies, headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO);
        }

        // Throttle memakai ThrottleRequests bawaan, yang menghormati
        // config('cache.limiter') — sekarang rantai 'failover' (Redis lalu
        // database). Counter tetap tersimpan di Redis selama Redis hidup,
        // jadi kuota masih berlaku sama untuk semua worker.
        //
        // throttleWithRedis() sengaja TIDAK dipakai: varian itu memanggil
        // EVAL langsung ke koneksi redis 'default' lewat DurationLimiter dan
        // mengabaikan config('cache.limiter'), sehingga satu kedipan Redis
        // membuat semua route ber-throttle — termasuk pengiriman laporan dan
        // login — menjawab 500. Dengan versi cache, Redis mati berarti
        // pembatasan turun ke database, bukan layanan yang ikut mati.

        $middleware->web(append: [
            SecurityHeaders::class,
            EnterpriseSecurity::class,
        ]);

        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $expiredSessionResponse = static function (Request $request) {
            $message = 'Sesi form sudah kedaluwarsa. Muat ulang halaman lalu kirim ulang data.';
            if ($request->expectsJson()) return response()->json(['message'=>$message],419);
            return back()->withInput($request->except(['_token','password','password_confirmation']))->withErrors(['session'=>$message]);
        };
        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($expiredSessionResponse) { return $expiredSessionResponse($request); });
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($expiredSessionResponse) { return $e->getStatusCode() === 419 ? $expiredSessionResponse($request) : null; });
    })
    ->booted(function (): void {
        RateLimiter::for('public-wizard', function (Request $request) {
            $device = $request->cookie('laporin_device_id') ?: ($request->ip() ?? 'unknown');
            return Limit::perMinute(30)->by('public-wizard-device:' . hash_hmac('sha256', (string) $device, config('app.key')));
        });

        /*
         * PENJAGA BANJIR PERMINTAAN saja (anti-bot/DoS) — BUKAN kuota laporan.
         *
         * Kuota bisnis "maksimal 5 laporan per perangkat dalam 2 jam" ditegakkan
         * di PublicReportController::store() dan hanya dihitung ketika laporan
         * benar-benar tersimpan.
         *
         * Sebelumnya batas 5-per-120-menit dipasang di sini sebagai middleware
         * route, sehingga SETIAP POST /lapor ikut dihitung — termasuk yang gagal
         * validasi atau salah CAPTCHA — dan begitu terlampaui pelapor dilempar ke
         * halaman 429 sehingga situs terasa mati. Batas per-IP juga berbahaya di
         * sekolah karena semua siswa berbagi satu IP publik.
         */
        RateLimiter::for(
            'public-reports',
            function (Request $request) {
                $ip = (string) ($request->ip() ?: 'unknown');

                return Limit::perMinute(30)
                    ->by('public-report-flood:' . hash_hmac('sha256', $ip, (string) config('app.key')));
            }
        );

        RateLimiter::for(
            'public-tracking',
            function (Request $request) {
                $ip = (string) ($request->ip() ?: 'unknown');
                $device = (string) ($request->cookie('laporin_device_id') ?: $ip);

                return [
                    /*
                     * Kuota utama dihitung per perangkat. Sebelumnya batas
                     * 10/menit hanya memakai IP mentah, sehingga seluruh sekolah
                     * yang keluar lewat satu IP publik saling menghabiskan kuota
                     * pelacakan satu sama lain.
                     */
                    Limit::perMinute(20)->by(
                        'public-tracking-device:' . hash_hmac('sha256', $device, (string) config('app.key'))
                    ),
                    /*
                     * Cookie perangkat dikendalikan klien, jadi batas per IP
                     * tetap dipertahankan sebagai penjaga banjir permintaan.
                     */
                    Limit::perMinute(60)->by(
                        'public-tracking-ip:' . hash_hmac('sha256', $ip, (string) config('app.key'))
                    ),
                ];
            }
        );
    })
    ->create();