<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectReportFormFix
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->routeIs('public.report')
            && $response->isSuccessful()
            && str_contains((string) $response->headers->get('Content-Type', ''), 'text/html')
        ) {
            $content = $response->getContent();

            if (is_string($content) && ! str_contains($content, 'js/laporin-report-fix.js')) {
                $version = @filemtime(public_path('js/laporin-report-fix.js')) ?: time();
                $script = '<script src="'.e(asset('js/laporin-report-fix.js')).'?v='.$version.'"></script>';
                $content = str_replace('</body>', $script.'</body>', $content);
                $response->setContent($content);
            }
        }

        return $response;
    }
}
