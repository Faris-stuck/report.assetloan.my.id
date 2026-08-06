<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnterpriseSecurity
{
    public function handle(Request $request, Closure $next): Response
    {
        $clientIp = $this->clientIp($request);

        // Global conservative rate limit per IP to mitigate abusive clients
        $key = 'enterprise:ip:'.$clientIp;
        $maxAttempts = 300; // short-term burst threshold
        $decay = 60; // seconds

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            Log::warning('Rate limit exceeded', ['ip' => $clientIp, 'path' => $request->path()]);

            return response('Too many requests', 429);
        }

        RateLimiter::hit($key, $decay);

        // Basic SQL injection heuristic scan against input payload
        $content = strtolower($request->getContent() ?: json_encode($request->all()));
        $suspiciousPatterns = ["union select", " or 1=1", "--", "/*", "*/", "sleep(", "benchmark(", "information_schema", "load_file("];
        foreach ($suspiciousPatterns as $pat) {
            if (strpos($content, $pat) !== false) {
                Log::warning('Suspicious payload detected', ['ip' => $clientIp, 'pattern' => $pat, 'path' => $request->path()]);

                return response('Bad request', 400);
            }
        }

        // Block requests from obviously malicious user agents
        $ua = strtolower($request->header('User-Agent', ''));
        $blockUa = ['sqlmap', 'wget', 'curl', 'python-requests', 'nikto', 'acunetix'];
        foreach ($blockUa as $bad) {
            if ($ua !== '' && str_contains($ua, $bad)) {
                Log::warning('Blocked bad user-agent', ['ip' => $clientIp, 'ua' => $ua]);

                return response('Forbidden', 403);
            }
        }

        return $next($request);
    }

    private function clientIp(Request $request): string
    {
        $cf = $request->headers->get('CF-Connecting-IP');
        if (is_string($cf) && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf;
        }

        $fwd = $request->headers->get('X-Forwarded-For');
        if (is_string($fwd)) {
            $first = trim(explode(',', $fwd)[0] ?? '');
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return $request->ip() ?? 'unknown';
    }
}
