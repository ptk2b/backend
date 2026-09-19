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

        if (!$user || !in_array($user->role, ['admin', 'hrd'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akses ditolak: Hanya Admin dan HRD yang diizinkan melakukan perubahan data sanksi dan SP.',
            ], 403);
        }

        return $next($request);
    }
}
