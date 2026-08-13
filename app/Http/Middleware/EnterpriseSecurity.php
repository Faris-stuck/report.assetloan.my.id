<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnterpriseSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $request->ip() ?? 'unknown';

        $key = 'enterprise:ip:'.$clientIp;
        $maxAttempts = 300;
        $decay = 60;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            Log::warning('Rate limit exceeded', [
                'ip' => $clientIp,
                'path' => $request->path(),
            ]);

            return response('Too many requests', 429);
        }

        RateLimiter::hit($key, $decay);

        $content = strtolower(
            $request->getContent() ?: json_encode($request->all())
        );

        $suspiciousPatterns = [
            'union select',
            ' or 1=1',
            '--',
            '/*',
            '*/',
            'sleep(',
            'benchmark(',
            'information_schema',
            'load_file(',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (strpos($content, $pattern) !== false) {
                Log::warning('Suspicious payload detected', [
                    'ip' => $clientIp,
                    'pattern' => $pattern,
                    'path' => $request->path(),
                ]);

                return response('Bad request', 400);
            }
        }

        return $next($request);
    }
}