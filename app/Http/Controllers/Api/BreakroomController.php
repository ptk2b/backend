<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeComicLibrary;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class BreakroomController extends Controller
{
    /**
     * Resolves the authenticated employee from Bearer token
     */
    private function getAuthEmployee(Request $request): ?Employee
    {
        $token = $request->bearerToken() ?: $request->query('t');
        if (!$token) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($payload) || ($payload['exp'] ?? 0) < time()) {
            return null;
        }

        return Employee::query()
            ->select(['id', 'nama_lengkap', 'nip', 'nik', 'jabatan', 'departemen', 'status_karyawan'])
            ->find($payload['id'] ?? 0);
    }

    /**
     * POST /public/breakroom/login
     * Login using Employee Name / NIP as identifier and NIP as password.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => 'required|string|max:100',
            'password'   => 'required|string|max:100',
        ]);

        $identifier = trim((string) $request->input('identifier'));
        $password   = trim((string) $request->input('password'));

        // Look for employee where NIP matches password
        // and identifier matches either NIP or Nama Lengkap (case-insensitive)
        $query = Employee::query()
            ->where(function ($q) use ($password) {
                $q->where('nip', $password)
                  ->orWhereRaw('REPLACE(nip, " ", "") = ?', [str_replace(' ', '', $password)]);
            });

        // Tolerant matching for identifier
        $emp = (clone $query)->where(function ($q) use ($identifier) {
            $q->where('nip', $identifier)
              ->orWhereRaw('LOWER(nama_lengkap) = ?', [Str::lower($identifier)])
              ->orWhere('nama_lengkap', 'LIKE', '%' . $identifier . '%');
        })->first();

        // Fallback: If only 1 employee has that NIP as password, allow login
        if (!$emp) {
            $candidates = $query->get();
            if ($candidates->count() === 1) {
                $emp = $candidates->first();
            }
        }

        if (!$emp) {
            return response()->json([
                'success' => false,
                'message' => 'NIP atau Nama Karyawan tidak cocok. Gunakan NIP terdaftar sebagai password.',
            ], 401);
        }

        // Generate stateless token valid for 90 days
        $token = Crypt::encryptString(json_encode([
            'id'   => $emp->id,
            'nip'  => $emp->nip,
            'nama' => $emp->nama_lengkap,
            'exp'  => time() + (86400 * 90),
        ]));

        return response()->json([
            'success'  => true,
            'message'  => 'Selamat datang di K2B Breakroom, ' . $emp->nama_lengkap . '!',
            'token'    => $token,
            'employee' => [
                'id'           => $emp->id,
                'nama_lengkap' => $emp->nama_lengkap,
                'nip'          => $emp->nip,
                'jabatan'      => $emp->jabatan,
                'departemen'   => $emp->departemen,
            ],
        ]);
    }

    /**
     * GET /public/breakroom/me
     */
    public function me(Request $request): JsonResponse
    {
        $emp = $this->getAuthEmployee($request);
        if (!$emp) {
            return response()->json(['success' => false, 'message' => 'Sesi berakhir, silakan login kembali.'], 401);
        }

        $stats = [
            'total_library' => EmployeeComicLibrary::where('employee_id', $emp->id)->count(),
            'reading'       => EmployeeComicLibrary::where('employee_id', $emp->id)->where('status', 'reading')->count(),
            'plan_to_read'  => EmployeeComicLibrary::where('employee_id', $emp->id)->where('status', 'plan_to_read')->count(),
            'completed'     => EmployeeComicLibrary::where('employee_id', $emp->id)->where('status', 'completed')->count(),
        ];

        $lastRead = EmployeeComicLibrary::where('employee_id', $emp->id)
            ->whereNotNull('last_read_at')
            ->orderByDesc('last_read_at')
            ->first();

        return response()->json([
            'success'   => true,
            'employee'  => [
                'id'           => $emp->id,
                'nama_lengkap' => $emp->nama_lengkap,
                'nip'          => $emp->nip,
                'jabatan'      => $emp->jabatan,
                'departemen'   => $emp->departemen,
            ],
            'stats'     => $stats,
            'last_read' => $lastRead,
        ]);
    }

    /**
     * GET /public/breakroom/library
     */
    public function getLibrary(Request $request): JsonResponse
    {
        $emp = $this->getAuthEmployee($request);
        if (!$emp) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $status = $request->query('status');
        $query = EmployeeComicLibrary::where('employee_id', $emp->id);

        if ($status && in_array($status, ['reading', 'plan_to_read', 'completed', 'on_hold'])) {
            $query->where('status', $status);
        }

        $items = $query->orderByDesc('last_read_at')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $items,
        ]);
    }

    /**
     * POST /public/breakroom/bookmark
     * Update bookmark status: reading, plan_to_read, completed, on_hold, or remove
     */
    public function updateBookmark(Request $request): JsonResponse
    {
        $emp = $this->getAuthEmployee($request);
        if (!$emp) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'comic_slug'  => 'required|string',
            'comic_title' => 'required|string',
            'comic_thumb' => 'nullable|string',
            'comic_type'  => 'nullable|string',
            'status'      => 'required|string|in:reading,plan_to_read,completed,on_hold,remove',
        ]);

        $comicSlug = trim((string) $request->input('comic_slug'));
        $status    = $request->input('status');

        if ($status === 'remove') {
            EmployeeComicLibrary::where('employee_id', $emp->id)
                ->where('comic_slug', $comicSlug)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Komik dihapus dari koleksi.',
                'status'  => null,
            ]);
        }

        $item = EmployeeComicLibrary::updateOrCreate(
            [
                'employee_id' => $emp->id,
                'comic_slug'  => Str::limit($comicSlug, 250, ''),
            ],
            [
                'comic_title' => Str::limit((string) $request->input('comic_title'), 250, ''),
                'comic_thumb' => $request->filled('comic_thumb') ? Str::limit((string) $request->input('comic_thumb'), 250, '') : null,
                'comic_type'  => Str::limit((string) $request->input('comic_type', 'manhwa'), 50, ''),
                'status'      => $status,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Status koleksi berhasil diperbarui.',
            'data'    => $item,
        ]);
    }

    /**
     * POST /public/breakroom/progress
     * Save reading progress & mark chapter as read
     */
    public function updateProgress(Request $request): JsonResponse
    {
        $emp = $this->getAuthEmployee($request);
        if (!$emp) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $request->validate([
            'comic_slug'         => 'required|string',
            'comic_title'        => 'required|string',
            'comic_thumb'        => 'nullable|string',
            'comic_type'         => 'nullable|string',
            'chapter_slug'       => 'nullable|string',
            'chapter_num'        => 'required|string',
            'chapter_title'      => 'nullable|string',
            'scroll_progress'    => 'nullable|integer|min:0|max:100',
        ]);

        $comicSlug   = Str::limit(trim((string) $request->input('comic_slug')), 250, '');
        $chapterNum  = Str::limit(trim((string) $request->input('chapter_num')), 50, '');
        $chapterSlug = Str::limit(trim((string) $request->input('chapter_slug', '')), 250, '');

        $item = EmployeeComicLibrary::firstOrNew([
            'employee_id' => $emp->id,
            'comic_slug'  => $comicSlug,
        ]);

        $item->comic_title        = Str::limit((string) $request->input('comic_title'), 250, '');
        if ($request->filled('comic_thumb')) {
            $item->comic_thumb = Str::limit((string) $request->input('comic_thumb'), 250, '');
        }
        $item->comic_type         = Str::limit((string) $request->input('comic_type', $item->comic_type ?: 'manhwa'), 50, '');
        $item->last_chapter_slug  = $chapterSlug;
        $item->last_chapter_num   = $chapterNum;
        $item->last_chapter_title = Str::limit((string) $request->input('chapter_title', 'Chapter ' . $chapterNum), 250, '');
        $item->scroll_progress    = (int) $request->input('scroll_progress', 0);
        $item->last_read_at       = Carbon::now();

        // Default to reading if newly added
        if (!$item->exists) {
            $item->status = 'reading';
        }

        // Add to read_chapters array if not already recorded
        $readChapters = is_array($item->read_chapters) ? $item->read_chapters : [];
        if (!in_array($chapterNum, $readChapters)) {
            $readChapters[] = $chapterNum;
        }
        $item->read_chapters = array_values(array_unique($readChapters));

        $item->save();

        return response()->json([
            'success' => true,
            'data'    => $item,
        ]);
    }

    /**
     * DELETE /public/breakroom/bookmark/{comicSlug}
     */
    public function removeBookmark(Request $request, string $comicSlug): JsonResponse
    {
        $emp = $this->getAuthEmployee($request);
        if (!$emp) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        EmployeeComicLibrary::where('employee_id', $emp->id)
            ->where('comic_slug', $comicSlug)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Komik dihapus dari koleksi.',
        ]);
    }
}
