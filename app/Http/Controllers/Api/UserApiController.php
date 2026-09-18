<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserApiController extends Controller
{
    /**
     * Get list of all users (Admin only)
     */
    public function index(Request $request): JsonResponse
    {
        $users = User::select('id', 'name', 'username', 'email', 'role', 'created_at')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $users,
        ]);
    }

    /**
     * Create a new user (Admin only)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|max:100|unique:users,username',
            'email'    => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:admin,viewer',
        ], [
            'name.required'     => 'Nama lengkap wajib diisi.',
            'username.required' => 'Username wajib diisi.',
            'username.unique'   => 'Username sudah terdaftar.',
            'password.required' => 'Password wajib diisi.',
            'password.min'      => 'Password minimal 6 karakter.',
            'role.required'     => 'Role wajib dipilih.',
            'role.in'           => 'Role hanya boleh admin atau viewer.',
        ]);

        $email = !empty($validated['email'])
            ? $validated['email']
            : strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $validated['username'])) . '@ptk2b.local';

        // Check if generated email conflicts
        if (User::where('email', $email)->exists()) {
            $email = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $validated['username'])) . '_' . time() . '@ptk2b.local';
        }

        $user = User::create([
            'name'     => $validated['name'],
            'username' => $validated['username'],
            'email'    => $email,
            'password' => Hash::make($validated['password']),
            'role'     => $validated['role'],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Akun pengguna berhasil dibuat!',
            'data'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'username'   => $user->username,
                'email'      => $user->email,
                'role'       => $user->role,
                'created_at' => $user->created_at,
            ],
        ], 201);
    }

    /**
     * Reset password for an existing user (Admin only)
     */
    public function resetPassword(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:6',
        ], [
            'password.required' => 'Password baru wajib diisi.',
            'password.min'      => 'Password baru minimal 6 karakter.',
        ]);

        $user = User::findOrFail($id);

        $user->password = Hash::make($validated['password']);
        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Password untuk pengguna '{$user->username}' berhasil direset!",
        ]);
    }

    /**
     * Delete user (Admin only, cannot delete self)
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $currentUser = $request->user();
        if ($currentUser && $currentUser->id == $id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif digunakan.',
            ], 400);
        }

        $user = User::findOrFail($id);
        $username = $user->username;
        $user->delete();

        return response()->json([
            'status'  => 'success',
            'message' => "Akun '{$username}' berhasil dihapus.",
        ]);
    }
}
