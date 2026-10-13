<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminOrHrd
{
    /**
     * Handle an incoming request for Admin or HRD roles.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (in_array($user->role, ['admin', 'hrd'])) {
            return $next($request);
        }

        // Check granular permission by URL route path
        $path = $request->path();
        if (str_contains($path, 'rfu') && $user->canAccessModule('rfu-unit', 'write')) {
            return $next($request);
        }
        if (str_contains($path, 'attendance') && $user->canAccessModule('monitoring-absensi', 'write')) {
            return $next($request);
        }
        if (str_contains($path, 'sanctions') && $user->canAccessModule('sp-karyawan', 'write')) {
            return $next($request);
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Akses ditolak: Anda tidak memiliki izin untuk mengubah data pada modul ini.',
        ], 403);

        return $next($request);
    }
}
