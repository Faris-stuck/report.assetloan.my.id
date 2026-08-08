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
        $trustedProxies = array_values(array_filter(
            array_map(
                'trim',
                explode(',', (string) env('TRUSTED_PROXIES', ''))
            )
        ));

        if ($trustedProxies !== []) {
            $middleware->trustProxies(
                at: $trustedProxies,
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
            );
        }

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

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 419);
            }

            return back()
                ->withInput(
                    $request->except([
                        '_token',
                        'password',
                        'password_confirmation',
                    ])
                )
                ->withErrors([
                    'session' => $message,
                ]);
        };

        $exceptions->render(
            function (
                TokenMismatchException $e,
                Request $request
            ) use ($expiredSessionResponse) {
                return $expiredSessionResponse($request);
            }
        );

        $exceptions->render(
            function (
                HttpExceptionInterface $e,
                Request $request
            ) use ($expiredSessionResponse) {
                if ($e->getStatusCode() !== 419) {
                    return null;
                }

                return $expiredSessionResponse($request);
            }
        );
    })
    ->booted(function (): void {
        RateLimiter::for(
            'public-reports',
            function (Request $request) {
                return Limit::perMinutes(30, 5)
                    ->by($request->ip() ?? 'unknown');
            }
        );

        RateLimiter::for(
            'public-tracking',
            function (Request $request) {
                return Limit::perMinute(10)
                    ->by($request->ip() ?? 'unknown');
            }
        );
    })
    ->create();