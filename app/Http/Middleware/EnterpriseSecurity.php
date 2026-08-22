<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;

class EnterpriseSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip() ?? 'unknown';

        // Browsers cannot reliably expose private/LAN IPs to the server.
        // Use a server-issued device identifier for per-device controls.
        $deviceId = $request->cookie('laporin_device_id');
        $hadDeviceCookie = is_string($deviceId) && preg_match('/^[A-Fa-f0-9-]{32,80}$/', $deviceId) === 1;
        if (! $hadDeviceCookie) {
            $deviceId = (string) Str::uuid();
            $request->cookies->set('laporin_device_id', $deviceId);
        }

        $key = 'enterprise:client:'.hash_hmac('sha256', $this->rateLimitSubject($clientIp, (string) $deviceId, $hadDeviceCookie), config('app.key'));
        $maxAttempts = 300;
        $decay = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            Log::warning('Rate limit exceeded', ['ip' => $clientIp, 'path' => $request->path()]);
            return response('Too many requests', 429);
        }

        RateLimiter::hit($key, $decay);

        // Do not reject arbitrary report text using SQL keyword matching.
        // Eloquent/query-builder parameterization is the application SQL boundary;
        // Cloudflare/WAF and route-specific throttles handle abusive traffic.
        $response = $next($request);

        if (! $hadDeviceCookie) {
            $response->headers->setCookie(cookie('laporin_device_id', $deviceId, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $response;
    }

    /**
     * Permintaan tanpa cookie perangkat yang sah selalu mendapat UUID baru di
     * atas. Kalau kunci pembatas ikut memakai UUID itu, klien yang membuang
     * cookie mendapat jatah 300 permintaan yang benar-benar baru setiap kali,
     * sehingga pembatas ini tidak pernah menahan siapa pun. Untuk permintaan
     * seperti itu kuncinya dijatuhkan ke IP; pengunjung wajar hanya melewati
     * jalur ini sekali sebelum cookie-nya tersimpan.
     */
    private function rateLimitSubject(string $clientIp, string $deviceId, bool $hadDeviceCookie): string
    {
        return $hadDeviceCookie ? 'device:'.$deviceId : 'ip:'.$clientIp;
    }
}
