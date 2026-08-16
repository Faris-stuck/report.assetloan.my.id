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
        $deviceId = $request->cookie('laporin_device_id');
        $hadDeviceCookie = is_string($deviceId) && $deviceId !== '';
        if (! is_string($deviceId) || ! preg_match('/^[A-Fa-f0-9-]{32,80}$/', $deviceId)) {
            $deviceId = (string) Str::uuid();
            $request->cookies->set('laporin_device_id', $deviceId);
        }

        $key = 'enterprise:device:'.hash_hmac('sha256', $deviceId, config('app.key'));
        $maxAttempts = 300;
        $decay = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            Log::warning('Rate limit exceeded', ['ip' => $clientIp, 'path' => $request->path()]);
            return response('Too many requests', 429);
        }

        RateLimiter::hit($key, $decay);
        $response = $next($request);

        if (! $hadDeviceCookie) {
            $response->headers->setCookie(cookie('laporin_device_id', $deviceId, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $response;
    }
}
