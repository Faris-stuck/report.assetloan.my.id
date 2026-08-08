<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\EnterpriseSecurity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class, EnterpriseSecurity::class]);
        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $expiredSessionResponse = static function (Request $request) {
            $message = 'Sesi form sudah kedaluwarsa. Muat ulang halaman lalu kirim ulang data.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 419);
            }

            return back()
                ->withInput($request->except(['_token', 'password', 'password_confirmation']))
                ->withErrors(['session' => $message]);
        };

        $exceptions->render(function (TokenMismatchException $e, Request $request) use ($expiredSessionResponse) {
            return $expiredSessionResponse($request);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($expiredSessionResponse) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            return $expiredSessionResponse($request);
        });
    })->booted(function (): void {
        $clientIp = static function (Request $request): string {
            $cfConnectingIp = $request->headers->get('CF-Connecting-IP');
            if (is_string($cfConnectingIp) && filter_var($cfConnectingIp, FILTER_VALIDATE_IP)) {
                return $cfConnectingIp;
            }

            $forwardedFor = $request->headers->get('X-Forwarded-For');
            if (is_string($forwardedFor)) {
                $firstForwardedIp = trim(explode(',', $forwardedFor)[0] ?? '');
                if (filter_var($firstForwardedIp, FILTER_VALIDATE_IP)) {
                    return $firstForwardedIp;
                }
            }

            return $request->ip() ?? 'unknown';
        };

        // Rate limiting rationale:
        // - 5 public reports per 30 minutes: Prevents spam/abuse while allowing legitimate reporting
        // - 10 tracking queries per minute: Prevents tracking endpoint enumeration attacks
        // Both limits use client IP for tracking (with CF-Connecting-IP proxy support)
        RateLimiter::for('public-reports', function (Request $request) use ($clientIp) {
            return Limit::perMinutes(30, 5)->by($clientIp($request));
        });

        RateLimiter::for('public-tracking', function (Request $request) use ($clientIp) {
            return Limit::perMinute(10)->by($clientIp($request));
        });
    })->create();
