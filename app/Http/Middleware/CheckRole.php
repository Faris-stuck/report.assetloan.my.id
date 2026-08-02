<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user && $user->is_active, 403, 'Akses tidak diizinkan.');

        $userRole = $this->normalizeRole((string) $user->role);
        $allowedRoles = array_map(fn (string $role): string => $this->normalizeRole($role), $roles);

        $superadminAllowed = $userRole === 'superadmin';

        abort_unless($superadminAllowed || in_array($userRole, $allowedRoles, true), 403, 'Akses tidak diizinkan.');

        return $next($request);
    }

    private function normalizeRole(string $role): string
    {
        return str_replace(['_', '-', ' '], '', strtolower($role));
    }
}
