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

        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akses ditolak: Akun Anda memiliki role Viewer (Read-Only) dan tidak diizinkan melakukan perubahan data.',
            ], 403);
        }

        return $next($request);
    }
}
