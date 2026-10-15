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

        // Tentukan level akses yang dibutuhkan:
        // Request GET/HEAD hanya butuh izin 'read' (Lihat Saja atau Bisa Edit)
        // Request POST/PUT/PATCH/DELETE butuh izin 'write' (Bisa Edit)
        $isReadRequest = in_array(strtoupper($request->method()), ['GET', 'HEAD']);
        $requiredLevel = $isReadRequest ? 'read' : 'write';

        // Check granular permission by URL route path
        $path = $request->path();
        if (str_contains($path, 'rfu') && $user->canAccessModule('rfu-unit', $requiredLevel)) {
            return $next($request);
        }
        if (str_contains($path, 'attendance') && $user->canAccessModule('monitoring-absensi', $requiredLevel)) {
            return $next($request);
        }
        if (str_contains($path, 'sanctions') && $user->canAccessModule('sp-karyawan', $requiredLevel)) {
            return $next($request);
        }
        if (str_contains($path, 'izin') && $user->canAccessModule('izin-lapangan', $requiredLevel)) {
            return $next($request);
        }

        $actionWord = $isReadRequest ? 'melihat' : 'mengubah';
        return response()->json([
            'status'  => 'error',
            'message' => "Akses ditolak: Anda tidak memiliki izin untuk {$actionWord} data pada modul ini.",
        ], 403);

        return $next($request);
    }
}
