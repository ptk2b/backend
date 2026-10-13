<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
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

        if ($user->role === 'admin') {
            return $next($request);
        }

        $path = $request->path();
        if (str_contains($path, 'fingerprint') && $user->canAccessModule('presensi-finger', 'write')) {
            return $next($request);
        }
        if (str_contains($path, 'employees') && $user->canAccessModule('manpower', 'write')) {
            return $next($request);
        }
        if (str_contains($path, 'memos') && $user->canAccessModule('memos', 'write')) {
            return $next($request);
        }
        if (str_contains($path, 'content') && $user->canAccessModule('content', 'write')) {
            return $next($request);
        }
        if (str_contains($path, 'careers') && $user->canAccessModule('careers', 'write')) {
            return $next($request);
        }
        if (str_contains($path, 'structure') && $user->canAccessModule('structure', 'write')) {
            return $next($request);
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'Akses ditolak: Hanya Administrator yang diizinkan melakukan tindakan ini.',
        ], 403);

        return $next($request);
    }
}
