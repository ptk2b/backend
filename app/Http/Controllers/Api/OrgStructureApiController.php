<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrgStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class OrgStructureApiController extends Controller
{
    /**
     * Get active organizational structure for public page.
     * GET /api/structure
     */
    public function index(Request $request): JsonResponse
    {
        $structures = OrgStructure::active()
            ->orderBy('level', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // If empty, return initial fallback seed items so UI looks populated immediately
        if ($structures->isEmpty()) {
            $structures = collect($this->getDefaultSeedData());
        }

        // Distinct divisions for filtering
        $divisions = $structures->pluck('division')->unique()->values();

        return response()->json([
            'status' => 'success',
            'data' => $structures,
            'divisions' => $divisions,
        ]);
    }

    /**
     * Get all structure data for admin panel.
     * GET /api/admin/structure
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $structures = OrgStructure::orderBy('level', 'asc')
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $structures,
        ]);
    }

    /**
     * Store new position/member.
     * POST /api/admin/structure
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'role'             => 'required|string|max:255',
            'division'         => 'required|string|max:255',
            'level'            => 'required|integer|min:1|max:5',
            'parent_id'        => 'nullable|integer|exists:org_structures,id',
            'photo'            => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'bio'              => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'sort_order'       => 'nullable|integer',
            'is_active'        => 'nullable|boolean',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $destinationPath = public_path('uploads/structure');
            
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
            
            $file->move($destinationPath, $filename);
            $photoPath = '/uploads/structure/' . $filename;
        }

        $structure = OrgStructure::create([
            'name'             => $validated['name'],
            'role'             => $validated['role'],
            'division'         => $validated['division'],
            'level'            => $validated['level'],
            'parent_id'        => $validated['parent_id'] ?? null,
            'photo_path'       => $photoPath,
            'bio'              => $validated['bio'] ?? null,
            'responsibilities' => $validated['responsibilities'] ?? null,
            'sort_order'       => $validated['sort_order'] ?? 0,
            'is_active'        => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Posisi struktur berhasil ditambahkan',
            'data' => $structure,
        ], 201);
    }

    /**
     * Update existing position/member.
     * POST/PUT /api/admin/structure/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        $structure = OrgStructure::findOrFail($id);

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'role'             => 'required|string|max:255',
            'division'         => 'required|string|max:255',
            'level'            => 'required|integer|min:1|max:5',
            'parent_id'        => 'nullable|integer',
            'photo'            => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'bio'              => 'nullable|string',
            'responsibilities' => 'nullable|string',
            'sort_order'       => 'nullable|integer',
            'is_active'        => 'nullable|boolean',
        ]);

        // Fix parent_id if it equals current id
        $parentId = $validated['parent_id'] ?? null;
        if ($parentId == $id) {
            $parentId = null;
        }

        if ($request->hasFile('photo')) {
            // Remove old photo if exists and local
            if ($structure->photo_path && File::exists(public_path($structure->photo_path))) {
                File::delete(public_path($structure->photo_path));
            }

            $file = $request->file('photo');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
            $destinationPath = public_path('uploads/structure');
            
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
            
            $file->move($destinationPath, $filename);
            $structure->photo_path = '/uploads/structure/' . $filename;
        }

        $structure->name = $validated['name'];
        $structure->role = $validated['role'];
        $structure->division = $validated['division'];
        $structure->level = $validated['level'];
        $structure->parent_id = $parentId;
        $structure->bio = $validated['bio'] ?? null;
        $structure->responsibilities = $validated['responsibilities'] ?? null;
        $structure->sort_order = $validated['sort_order'] ?? 0;
        
        if ($request->has('is_active')) {
            $structure->is_active = $request->boolean('is_active');
        }

        $structure->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Posisi struktur berhasil diperbarui',
            'data' => $structure,
        ]);
    }

    /**
     * Delete position/member.
     * DELETE /api/admin/structure/{id}
     */
    public function destroy($id): JsonResponse
    {
        $structure = OrgStructure::findOrFail($id);

        if ($structure->photo_path && File::exists(public_path($structure->photo_path))) {
            File::delete(public_path($structure->photo_path));
        }

        $structure->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Posisi struktur berhasil dihapus',
        ]);
    }

    /**
     * Default seed data for initial setup.
     */
    private function getDefaultSeedData(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Drs. H. Ahmad Sudrajat, M.M.',
                'role' => 'Komisaris Utama',
                'division' => 'Dewan Komisaris',
                'level' => 1,
                'parent_id' => null,
                'photo_path' => null,
                'bio' => 'Berpengalaman lebih dari 25 tahun dalam pengawasan tata kelola perusahaan dan kepemimpinan strategis.',
                'responsibilities' => 'Mengawasi kebijakan direksi dan memberikan arahan strategis jangka panjang perusahaan.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'id' => 2,
                'name' => 'Ir. Budi Santoso, M.T.',
                'role' => 'Direktur Utama',
                'division' => 'Direksi',
                'level' => 2,
                'parent_id' => 1,
                'photo_path' => null,
                'bio' => 'Memimpin visi operasional dan ekspansi bisnis PT. Karya Kembar Bersama secara komprehensif.',
                'responsibilities' => 'Memimpin seluruh divisi operasional dan bertanggung jawab atas kinerja komersial perusahaan.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'id' => 3,
                'name' => 'Hendra Wijaya, S.E., Ak.',
                'role' => 'Direktur Keuangan & SDM',
                'division' => 'Direksi',
                'level' => 2,
                'parent_id' => 2,
                'photo_path' => null,
                'bio' => 'Pakar dalam manajemen risiko keuangan, efisiensi anggaran, dan pengembangan SDM unggul.',
                'responsibilities' => 'Mengendalikan alokasi anggaran perusahaan, manajemen kas, dan strategi pengembangan talenta.',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'id' => 4,
                'name' => 'Bambang Kusuma, S.T.',
                'role' => 'General Manager Operasional',
                'division' => 'Operasional',
                'level' => 3,
                'parent_id' => 2,
                'photo_path' => null,
                'bio' => 'Memiliki rekam jejak panjang dalam eksekusi proyek lapangan dan kendali mutu operasional.',
                'responsibilities' => 'Mengkoordinasikan tim teknis dan memastikan standar K3LH serta SLA proyek terpenuhi.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'id' => 5,
                'name' => 'Rina Kartika, S.E.',
                'role' => 'Manager Keuangan & Akuntansi',
                'division' => 'Keuangan',
                'level' => 3,
                'parent_id' => 3,
                'photo_path' => null,
                'bio' => 'Mengelola pelaporan keuangan, perpajakan, dan audit internal perusahaan secara transparan.',
                'responsibilities' => 'Penyusunan laporan keuangan bulanan, manajemen payroll, dan kepatuhan regulasi pajak.',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'id' => 6,
                'name' => 'Dewi Anggraini, S.H., M.H.',
                'role' => 'Manager HR & Legal',
                'division' => 'HR & Legal',
                'level' => 3,
                'parent_id' => 3,
                'photo_path' => null,
                'bio' => 'Spesialis hukum korporasi, perizinan usaha, dan manajemen hubungan kepegawaian.',
                'responsibilities' => 'Pengelolaan kontrak kerja, perizinan instansi, recruitment, dan legalitas korporasi.',
                'sort_order' => 2,
                'is_active' => true,
            ],
        ];
    }
}
