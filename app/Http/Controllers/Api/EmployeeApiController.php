<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeFamily;
use App\Models\ContractHistory;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class EmployeeApiController extends Controller
{
    // ============================
    // EMPLOYEE CRUD
    // ============================

    /**
     * List all employees with search + multi filters + families count.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::withCount('families')->with('families');

        // Search by nama_lengkap, nip, nik, jabatan, departemen, email, no_telp
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nip', 'like', "%{$search}%")
                  ->orWhere('nik', 'like', "%{$search}%")
                  ->orWhere('jabatan', 'like', "%{$search}%")
                  ->orWhere('departemen', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('no_telp', 'like', "%{$search}%")
                  ->orWhere('nomor_kartu_keluarga', 'like', "%{$search}%");
            });
        }

        // Filter by status_hubungan_kerja (PKWT / PKWTT)
        if ($statusHub = $request->input('status_hubungan_kerja')) {
            $query->where('status_hubungan_kerja', $statusHub);
        }

        // Filter by status_karyawan (ACTIVE / NON ACTIVE)
        if ($statusKar = $request->input('status_karyawan')) {
            $query->where('status_karyawan', $statusKar);
        }

        // Filter by departemen
        if ($dept = $request->input('departemen')) {
            $query->where('departemen', $dept);
        }

        // Filter by jabatan
        if ($jabatan = $request->input('jabatan')) {
            $query->where('jabatan', $jabatan);
        }

        // Filter by lokal_nonlokal
        if ($lokal = $request->input('lokal_nonlokal')) {
            $query->where('lokal_nonlokal', $lokal);
        }

        // Filter by jenis_kelamin
        if ($gender = $request->input('jenis_kelamin')) {
            $query->where('jenis_kelamin', $gender);
        }

        $employees = $query->orderBy('nama_lengkap', 'asc')->get();

        return response()->json($employees);
    }

    /**
     * Get global employee statistics for stat cards.
     */
    public function stats(): JsonResponse
    {
        $total = Employee::count();
        $active = Employee::where('status_karyawan', 'ACTIVE')->count();
        $nonActive = Employee::where('status_karyawan', 'NON ACTIVE')->count();
        $pkwt = Employee::where('status_hubungan_kerja', 'PKWT')->count();
        $pkwtt = Employee::where('status_hubungan_kerja', 'PKWTT')->count();

        return response()->json([
            'total'      => $total,
            'active'     => $active,
            'non_active' => $nonActive,
            'pkwt'       => $pkwt,
            'pkwtt'      => $pkwtt,
        ]);
    }

    /**
     * Get unique list of existing jabatan/positions for dropdown filter.
     */
    public function positions(): JsonResponse
    {
        $positions = Employee::whereNotNull('jabatan')
            ->where('jabatan', '!=', '')
            ->distinct()
            ->pluck('jabatan')
            ->sort()
            ->values();

        return response()->json($positions);
    }

    /**
     * Show single employee with contract histories and families.
     */
    public function show(int $id): JsonResponse
    {
        $employee = Employee::with(['contractHistories', 'families'])->findOrFail($id);
        return response()->json($employee);
    }

    /**
     * Create new employee.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nama_lengkap'                  => 'required|string|max:255',
            'nip'                           => 'nullable|string|max:100',
            'nik'                           => 'nullable|string|max:100',
            'bendera'                       => 'nullable|string|max:100',
            'kode'                          => 'nullable|string|max:100',
            'pisat'                         => 'nullable|string|max:100',
            'peserta'                       => 'nullable|string|max:100',
            'jabatan'                       => 'nullable|string|max:255',
            'departemen'                    => 'nullable|string|max:255',
            'in'                            => 'nullable|date',
            'outtoday'                      => 'nullable|date',
            'outhal'                        => 'nullable|string|max:255',
            'kontrak'                       => 'nullable|string|max:100',
            'masa_kerja'                    => 'nullable|string|max:100',
            'status_hubungan_kerja'         => 'nullable|string|max:50',
            'status_karyawan'               => 'nullable|string|max:50',
            'mutasi_pt_jabatan'             => 'nullable|string|max:255',
            'lama_mutasi'                   => 'nullable|string|max:100',
            'no_telp'                       => 'nullable|string|max:50',
            'email'                         => 'nullable|string|max:255',
            'npwp'                          => 'nullable|string|max:100',
            'pendidikan_terakhir'           => 'nullable|string|max:100',
            'suku'                          => 'nullable|string|max:100',
            'agama'                         => 'nullable|string|max:100',
            'nomor_kartu_keluarga'          => 'nullable|string|max:100',
            'tempat_lahir'                  => 'nullable|string|max:255',
            'tanggal_lahir'                 => 'nullable|date',
            'usia'                          => 'nullable|integer',
            'jenis_kelamin'                 => 'nullable|string|max:50',
            'status_kawin'                  => 'nullable|string|max:100',
            'tanggal_perkawinan_perceraian' => 'nullable|date',
            'lokal_nonlokal'                => 'nullable|string|max:50',
            'kewarganegaraan'               => 'nullable|string|max:50',
            'alamat'                        => 'nullable|string',
            'rt'                            => 'nullable|string|max:20',
            'rw'                            => 'nullable|string|max:20',
            'kelurahan'                     => 'nullable|string|max:150',
            'kecamatan'                     => 'nullable|string|max:150',
            'kabupaten'                     => 'nullable|string|max:150',
            'provinsi'                      => 'nullable|string|max:150',
            'kode_pos'                      => 'nullable|string|max:20',
            'domisili'                      => 'nullable|string|max:255',
            'nama_ayah'                     => 'nullable|string|max:255',
            'nama_ibu'                      => 'nullable|string|max:255',
            'nomor_bpjstk'                  => 'nullable|string|max:100',
            'nomor_bpjs_kis_peserta'        => 'nullable|string|max:100',
            'nomor_bpjs_kis_anggota_keluarga' => 'nullable|string|max:100',
            'jenis_mutasi'                  => 'nullable|string|max:100',
            'pisat_bpjs'                    => 'nullable|string|max:100',
            'alamat_tempat_tinggal_bpjs'    => 'nullable|string',
            'kode_faskes_tk_1'              => 'nullable|string|max:100',
            'nama_faskes_tk_1'              => 'nullable|string|max:255',
            'kode_faskes_dokter_gigi'       => 'nullable|string|max:100',
            'nama_faskes_dokter_gigi'       => 'nullable|string|max:255',
            'nomor_telepon_rumus'           => 'nullable|string|max:100',
            'email_rumus'                   => 'nullable|string|max:255',
            'npp'                           => 'nullable|string|max:100',
            'gaji_pokok_tunjangan_tetap'    => 'nullable|string|max:100',
            'kewarganegaraan_bpjs'          => 'nullable|string|max:50',
            'sub_cabang'                    => 'nullable|string|max:150',
            'catatan'                       => 'nullable|string',
            'sk_file'                       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('sk_file')) {
            $data['sk_path'] = $request->file('sk_file')->store('employee-sk', 'public');
        }
        unset($data['sk_file']);

        if (!empty($data['departemen'])) {
            Department::firstOrCreate(['name' => trim($data['departemen'])]);
        }

        $employee = Employee::create($data);

        return response()->json($employee, 201);
    }

    /**
     * Update employee.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nama_lengkap'                  => 'required|string|max:255',
            'nip'                           => 'nullable|string|max:100',
            'nik'                           => 'nullable|string|max:100',
            'bendera'                       => 'nullable|string|max:100',
            'kode'                          => 'nullable|string|max:100',
            'pisat'                         => 'nullable|string|max:100',
            'peserta'                       => 'nullable|string|max:100',
            'jabatan'                       => 'nullable|string|max:255',
            'departemen'                    => 'nullable|string|max:255',
            'in'                            => 'nullable|date',
            'outtoday'                      => 'nullable|date',
            'outhal'                        => 'nullable|string|max:255',
            'kontrak'                       => 'nullable|string|max:100',
            'masa_kerja'                    => 'nullable|string|max:100',
            'status_hubungan_kerja'         => 'nullable|string|max:50',
            'status_karyawan'               => 'nullable|string|max:50',
            'mutasi_pt_jabatan'             => 'nullable|string|max:255',
            'lama_mutasi'                   => 'nullable|string|max:100',
            'no_telp'                       => 'nullable|string|max:50',
            'email'                         => 'nullable|string|max:255',
            'npwp'                          => 'nullable|string|max:100',
            'pendidikan_terakhir'           => 'nullable|string|max:100',
            'suku'                          => 'nullable|string|max:100',
            'agama'                         => 'nullable|string|max:100',
            'nomor_kartu_keluarga'          => 'nullable|string|max:100',
            'tempat_lahir'                  => 'nullable|string|max:255',
            'tanggal_lahir'                 => 'nullable|date',
            'usia'                          => 'nullable|integer',
            'jenis_kelamin'                 => 'nullable|string|max:50',
            'status_kawin'                  => 'nullable|string|max:100',
            'tanggal_perkawinan_perceraian' => 'nullable|date',
            'lokal_nonlokal'                => 'nullable|string|max:50',
            'kewarganegaraan'               => 'nullable|string|max:50',
            'alamat'                        => 'nullable|string',
            'rt'                            => 'nullable|string|max:20',
            'rw'                            => 'nullable|string|max:20',
            'kelurahan'                     => 'nullable|string|max:150',
            'kecamatan'                     => 'nullable|string|max:150',
            'kabupaten'                     => 'nullable|string|max:150',
            'provinsi'                      => 'nullable|string|max:150',
            'kode_pos'                      => 'nullable|string|max:20',
            'domisili'                      => 'nullable|string|max:255',
            'nama_ayah'                     => 'nullable|string|max:255',
            'nama_ibu'                      => 'nullable|string|max:255',
            'nomor_bpjstk'                  => 'nullable|string|max:100',
            'nomor_bpjs_kis_peserta'        => 'nullable|string|max:100',
            'nomor_bpjs_kis_anggota_keluarga' => 'nullable|string|max:100',
            'jenis_mutasi'                  => 'nullable|string|max:100',
            'pisat_bpjs'                    => 'nullable|string|max:100',
            'alamat_tempat_tinggal_bpjs'    => 'nullable|string',
            'kode_faskes_tk_1'              => 'nullable|string|max:100',
            'nama_faskes_tk_1'              => 'nullable|string|max:255',
            'kode_faskes_dokter_gigi'       => 'nullable|string|max:100',
            'nama_faskes_dokter_gigi'       => 'nullable|string|max:255',
            'nomor_telepon_rumus'           => 'nullable|string|max:100',
            'email_rumus'                   => 'nullable|string|max:255',
            'npp'                           => 'nullable|string|max:100',
            'gaji_pokok_tunjangan_tetap'    => 'nullable|string|max:100',
            'kewarganegaraan_bpjs'          => 'nullable|string|max:50',
            'sub_cabang'                    => 'nullable|string|max:150',
            'catatan'                       => 'nullable|string',
            'sk_file'                       => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('sk_file')) {
            if ($employee->sk_path) {
                Storage::disk('public')->delete($employee->sk_path);
            }
            $data['sk_path'] = $request->file('sk_file')->store('employee-sk', 'public');
        }
        unset($data['sk_file']);

        if (!empty($data['departemen'])) {
            Department::firstOrCreate(['name' => trim($data['departemen'])]);
        }

        $employee->update($data);

        return response()->json($employee);
    }

    /**
     * Delete employee.
     */
    public function destroy(int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        if ($employee->sk_path) {
            Storage::disk('public')->delete($employee->sk_path);
        }

        foreach ($employee->contractHistories as $history) {
            if ($history->sk_path) {
                Storage::disk('public')->delete($history->sk_path);
            }
        }

        $employee->delete();

        return response()->json(['message' => 'Data karyawan berhasil dihapus']);
    }

    // ============================
    // EXPIRING CONTRACTS
    // ============================

    public function expiring(Request $request): JsonResponse
    {
        $days = $request->input('days', 30);
        $employees = Employee::expiringSoon($days)->orderBy('outtoday', 'asc')->get();

        $employees->each(function ($emp) {
            if ($emp->outtoday) {
                $emp->days_remaining = Carbon::today()->diffInDays($emp->outtoday, false);
            }
        });

        return response()->json($employees);
    }

    // ============================
    // BATCH IMPORT (WITH SMART FAMILY SEPARATION)
    // ============================

    public function importBatch(Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return response()->json(['message' => 'Data import kosong'], 422);
        }

        $importedEmployees = 0;
        $importedFamilies = 0;
        $skipped = 0;
        $errorDetails = [];

        $currentEmployee = null;

        $parseDate = function ($val) {
            if (empty($val)) return null;
            try {
                return Carbon::parse($val)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        };

        foreach ($items as $index => $row) {
            $lineNum = $index + 1;

            $nama = trim($row['nama_lengkap'] ?? $row['nama'] ?? '');
            $nip = trim($row['nip'] ?? '');
            $nik = trim($row['nik'] ?? '');
            $jabatan = trim($row['jabatan'] ?? '');
            $pisat = trim($row['pisat'] ?? $row['pisat_bpjs'] ?? '');
            $noKk = trim($row['nomor_kartu_keluarga'] ?? '');

            if (empty($nama)) {
                $skipped++;
                $errorDetails[] = "Baris {$lineNum}: Nama kosong";
                continue;
            }

            // Determine if this row is a Family Member (Istri / Suami / Anak):
            // It is a family member if:
            // 1. Explicitly marked as is_family in row data, OR
            // 2. NIP is empty AND Jabatan is empty AND ($currentEmployee is present AND ($pisat in ['2','3','4','SUAMI','ISTRI','ANAK'] or empty noKk/matching noKk))
            $isExplicitFamily = !empty($row['is_family']) || $row['is_family'] === true;
            $isPisatFamily = in_array($pisat, ['2', '3', '4', '2. SUAMI', '3. ISTRI', '4. ANAK', 'SUAMI', 'ISTRI', 'ANAK']);
            $isImplicitFamily = empty($nip) && empty($jabatan) && empty($row['in']) && ($currentEmployee !== null);

            $isFamilyRow = $isExplicitFamily || ($isImplicitFamily && ($isPisatFamily || empty($noKk) || ($currentEmployee && $currentEmployee->nomor_kartu_keluarga === $noKk)));

            if ($isFamilyRow && $currentEmployee) {
                // ================================
                // INSERT AS EMPLOYEE FAMILY MEMBER
                // ================================
                try {
                    // Infer Hubungan
                    $hubungan = 'ANGGOTA KELUARGA';
                    if (!empty($row['hubungan'])) {
                        $hubungan = trim($row['hubungan']);
                    } elseif (str_contains($pisat, '2') || str_contains(strtoupper($pisat), 'SUAMI')) {
                        $hubungan = 'SUAMI';
                    } elseif (str_contains($pisat, '3') || str_contains(strtoupper($pisat), 'ISTRI')) {
                        $hubungan = 'ISTRI';
                    } elseif (str_contains($pisat, '4') || str_contains(strtoupper($pisat), 'ANAK')) {
                        $hubungan = 'ANAK';
                    } else {
                        $gender = strtoupper(trim($row['jenis_kelamin'] ?? ''));
                        $statusKawin = strtoupper(trim($row['status_kawin'] ?? ''));
                        $usia = !empty($row['usia']) ? (int) $row['usia'] : null;

                        if ((str_contains($gender, 'PEREMPUAN') || str_contains($gender, '2')) && (str_contains($statusKawin, 'KAWIN') || str_contains($statusKawin, 'MENIKAH'))) {
                            $hubungan = 'ISTRI';
                        } elseif ((str_contains($gender, 'LAKI') || str_contains($gender, '1')) && (str_contains($statusKawin, 'KAWIN') || str_contains($statusKawin, 'MENIKAH'))) {
                            $hubungan = 'SUAMI';
                        } elseif ($usia !== null && $usia <= 23) {
                            $hubungan = 'ANAK';
                        }
                    }

                    EmployeeFamily::create([
                        'employee_id'             => $currentEmployee->id,
                        'nama_lengkap'            => $nama,
                        'hubungan'                => $hubungan,
                        'pisat'                   => $pisat ?: null,
                        'nik'                     => $nik ?: null,
                        'tempat_lahir'            => !empty($row['tempat_lahir']) ? trim($row['tempat_lahir']) : null,
                        'tanggal_lahir'           => $parseDate($row['tanggal_lahir'] ?? null),
                        'usia'                    => !empty($row['usia']) ? (int) $row['usia'] : null,
                        'jenis_kelamin'           => !empty($row['jenis_kelamin']) ? trim($row['jenis_kelamin']) : null,
                        'status_kawin'            => !empty($row['status_kawin']) ? trim($row['status_kawin']) : null,
                        'nomor_bpjs_kis'          => !empty($row['nomor_bpjs_kis_anggota_keluarga']) ? trim($row['nomor_bpjs_kis_anggota_keluarga']) : (!empty($row['nomor_bpjs_kis']) ? trim($row['nomor_bpjs_kis']) : null),
                        'kode_faskes_tk_1'        => !empty($row['kode_faskes_tk_1']) ? trim($row['kode_faskes_tk_1']) : null,
                        'nama_faskes_tk_1'        => !empty($row['nama_faskes_tk_1']) ? trim($row['nama_faskes_tk_1']) : null,
                        'kode_faskes_dokter_gigi' => !empty($row['kode_faskes_dokter_gigi']) ? trim($row['kode_faskes_dokter_gigi']) : null,
                        'nama_faskes_dokter_gigi' => !empty($row['nama_faskes_dokter_gigi']) ? trim($row['nama_faskes_dokter_gigi']) : null,
                        'alamat'                  => !empty($row['alamat']) ? trim($row['alamat']) : $currentEmployee->alamat,
                    ]);

                    $importedFamilies++;
                } catch (\Exception $e) {
                    $skipped++;
                    $errorDetails[] = "Baris {$lineNum} (Keluarga {$nama}): " . $e->getMessage();
                }
            } else {
                // ================================
                // INSERT AS PRINCIPAL EMPLOYEE
                // ================================

                // Check duplicate by NIP if present
                if (!empty($nip) && Employee::where('nip', $nip)->exists()) {
                    // If exists, set as current employee so subsequent family rows can still attach if needed
                    $currentEmployee = Employee::where('nip', $nip)->first();
                    $skipped++;
                    $errorDetails[] = "Baris {$lineNum}: NIP \"{$nip}\" ({$nama}) sudah ada";
                    continue;
                }

                $dept = !empty($row['departemen']) ? trim($row['departemen']) : null;
                if ($dept) {
                    Department::firstOrCreate(['name' => $dept]);
                }

                $outhalVal = !empty($row['outhal']) ? trim($row['outhal']) : null;
                $statusKarRaw = strtoupper(trim($row['status_karyawan'] ?? ''));
                $statusHubRaw = strtoupper(trim($row['status_hubungan_kerja'] ?? ''));

                $isNonActive = (!empty($outhalVal) && $outhalVal !== '-')
                    || str_contains($statusKarRaw, 'NON')
                    || str_contains($statusKarRaw, 'TIDAK')
                    || str_contains($statusKarRaw, 'KELUAR')
                    || str_contains($statusKarRaw, 'PHK')
                    || str_contains($statusKarRaw, 'RESIGN')
                    || str_contains($statusKarRaw, 'OFF');

                $statusKar = $isNonActive ? 'NON ACTIVE' : 'ACTIVE';
                $statusHub = str_contains($statusHubRaw, 'PKWTT') || str_contains($statusHubRaw, 'TETAP') ? 'PKWTT' : 'PKWT';

                try {
                    $newEmployee = Employee::create([
                        'bendera'                       => !empty($row['bendera']) ? trim($row['bendera']) : null,
                        'kode'                          => !empty($row['kode']) ? trim($row['kode']) : null,
                        'pisat'                         => !empty($row['pisat']) ? trim($row['pisat']) : null,
                        'peserta'                       => !empty($row['peserta']) ? trim($row['peserta']) : null,
                        'nip'                           => $nip ?: null,
                        'jabatan'                       => !empty($row['jabatan']) ? trim($row['jabatan']) : null,
                        'departemen'                    => $dept,
                        'in'                            => $parseDate($row['in'] ?? null),
                        'outtoday'                      => $parseDate($row['outtoday'] ?? null),
                        'outhal'                        => $outhalVal,
                        'kontrak'                       => !empty($row['kontrak']) ? trim($row['kontrak']) : null,
                        'masa_kerja'                    => !empty($row['masa_kerja']) ? trim($row['masa_kerja']) : null,
                        'status_hubungan_kerja'         => $statusHub,
                        'status_karyawan'               => $statusKar,
                        'mutasi_pt_jabatan'             => !empty($row['mutasi_pt_jabatan']) ? trim($row['mutasi_pt_jabatan']) : null,
                        'lama_mutasi'                   => !empty($row['lama_mutasi']) ? trim($row['lama_mutasi']) : null,
                        'no_telp'                       => !empty($row['no_telp']) ? trim($row['no_telp']) : null,
                        'email'                         => !empty($row['email']) ? trim($row['email']) : null,
                        'npwp'                          => !empty($row['npwp']) ? trim($row['npwp']) : null,
                        'pendidikan_terakhir'           => !empty($row['pendidikan_terakhir']) ? trim($row['pendidikan_terakhir']) : null,
                        'suku'                          => !empty($row['suku']) ? trim($row['suku']) : null,
                        'agama'                         => !empty($row['agama']) ? trim($row['agama']) : null,
                        'nomor_kartu_keluarga'          => $noKk ?: null,
                        'nik'                           => $nik ?: null,
                        'nama_lengkap'                  => $nama,
                        'tempat_lahir'                  => !empty($row['tempat_lahir']) ? trim($row['tempat_lahir']) : null,
                        'tanggal_lahir'                 => $parseDate($row['tanggal_lahir'] ?? null),
                        'usia'                          => !empty($row['usia']) ? (int) $row['usia'] : null,
                        'jenis_kelamin'                 => !empty($row['jenis_kelamin']) ? trim($row['jenis_kelamin']) : null,
                        'status_kawin'                  => !empty($row['status_kawin']) ? trim($row['status_kawin']) : null,
                        'tanggal_perkawinan_perceraian' => $parseDate($row['tanggal_perkawinan_perceraian'] ?? null),
                        'lokal_nonlokal'                => !empty($row['lokal_nonlokal']) ? trim($row['lokal_nonlokal']) : null,
                        'kewarganegaraan'               => !empty($row['kewarganegaraan']) ? trim($row['kewarganegaraan']) : 'WNI',
                        'alamat'                        => !empty($row['alamat']) ? trim($row['alamat']) : null,
                        'rt'                            => !empty($row['rt']) ? trim($row['rt']) : null,
                        'rw'                            => !empty($row['rw']) ? trim($row['rw']) : null,
                        'kelurahan'                     => !empty($row['kelurahan']) ? trim($row['kelurahan']) : null,
                        'kecamatan'                     => !empty($row['kecamatan']) ? trim($row['kecamatan']) : null,
                        'kabupaten'                     => !empty($row['kabupaten']) ? trim($row['kabupaten']) : null,
                        'provinsi'                      => !empty($row['provinsi']) ? trim($row['provinsi']) : null,
                        'kode_pos'                      => !empty($row['kode_pos']) ? trim($row['kode_pos']) : null,
                        'domisili'                      => !empty($row['domisili']) ? trim($row['domisili']) : null,
                        'nama_ayah'                     => !empty($row['nama_ayah']) ? trim($row['nama_ayah']) : null,
                        'nama_ibu'                      => !empty($row['nama_ibu']) ? trim($row['nama_ibu']) : null,
                        'nomor_bpjstk'                  => !empty($row['nomor_bpjstk']) ? trim($row['nomor_bpjstk']) : null,
                        'nomor_bpjs_kis_peserta'        => !empty($row['nomor_bpjs_kis_peserta']) ? trim($row['nomor_bpjs_kis_peserta']) : null,
                        'nomor_bpjs_kis_anggota_keluarga' => !empty($row['nomor_bpjs_kis_anggota_keluarga']) ? trim($row['nomor_bpjs_kis_anggota_keluarga']) : null,
                        'jenis_mutasi'                  => !empty($row['jenis_mutasi']) ? trim($row['jenis_mutasi']) : null,
                        'pisat_bpjs'                    => !empty($row['pisat_bpjs']) ? trim($row['pisat_bpjs']) : null,
                        'alamat_tempat_tinggal_bpjs'    => !empty($row['alamat_tempat_tinggal_bpjs']) ? trim($row['alamat_tempat_tinggal_bpjs']) : null,
                        'kode_faskes_tk_1'              => !empty($row['kode_faskes_tk_1']) ? trim($row['kode_faskes_tk_1']) : null,
                        'nama_faskes_tk_1'              => !empty($row['nama_faskes_tk_1']) ? trim($row['nama_faskes_tk_1']) : null,
                        'kode_faskes_dokter_gigi'       => !empty($row['kode_faskes_dokter_gigi']) ? trim($row['kode_faskes_dokter_gigi']) : null,
                        'nama_faskes_dokter_gigi'       => !empty($row['nama_faskes_dokter_gigi']) ? trim($row['nama_faskes_dokter_gigi']) : null,
                        'nomor_telepon_rumus'           => !empty($row['nomor_telepon_rumus']) ? trim($row['nomor_telepon_rumus']) : null,
                        'email_rumus'                   => !empty($row['email_rumus']) ? trim($row['email_rumus']) : null,
                        'npp'                           => !empty($row['npp']) ? trim($row['npp']) : null,
                        'gaji_pokok_tunjangan_tetap'    => !empty($row['gaji_pokok_tunjangan_tetap']) ? trim($row['gaji_pokok_tunjangan_tetap']) : null,
                        'kewarganegaraan_bpjs'          => !empty($row['kewarganegaraan_bpjs']) ? trim($row['kewarganegaraan_bpjs']) : null,
                        'sub_cabang'                    => !empty($row['sub_cabang']) ? trim($row['sub_cabang']) : null,
                        'catatan'                       => !empty($row['catatan']) ? trim($row['catatan']) : null,
                    ]);

                    $currentEmployee = $newEmployee;
                    $importedEmployees++;
                } catch (\Exception $e) {
                    $skipped++;
                    $errorDetails[] = "Baris {$lineNum} (Karyawan {$nama}): " . $e->getMessage();
                }
            }
        }

        return response()->json([
            'message'             => "{$importedEmployees} data karyawan dan {$importedFamilies} anggota keluarga berhasil diproses! ({$skipped} dilewati)",
            'imported'            => $importedEmployees,
            'imported_employees'  => $importedEmployees,
            'imported_families'   => $importedFamilies,
            'skipped'             => $skipped,
            'error_details'       => $errorDetails,
        ]);
    }

    // ============================
    // FAMILY CRUD
    // ============================

    public function storeFamily(Request $request, int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $validator = Validator::make($request->all(), [
            'nama_lengkap'     => 'required|string|max:255',
            'hubungan'         => 'required|string|max:100',
            'nik'              => 'nullable|string|max:100',
            'tempat_lahir'     => 'nullable|string|max:255',
            'tanggal_lahir'    => 'nullable|date',
            'usia'             => 'nullable|integer',
            'jenis_kelamin'    => 'nullable|string|max:50',
            'status_kawin'     => 'nullable|string|max:100',
            'nomor_bpjs_kis'   => 'nullable|string|max:100',
            'kode_faskes_tk_1' => 'nullable|string|max:100',
            'nama_faskes_tk_1' => 'nullable|string|max:255',
            'alamat'           => 'nullable|string',
            'catatan'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();
        $data['employee_id'] = $employee->id;

        $family = EmployeeFamily::create($data);

        return response()->json($family, 201);
    }

    public function updateFamily(Request $request, int $id): JsonResponse
    {
        $family = EmployeeFamily::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'nama_lengkap'     => 'required|string|max:255',
            'hubungan'         => 'required|string|max:100',
            'nik'              => 'nullable|string|max:100',
            'tempat_lahir'     => 'nullable|string|max:255',
            'tanggal_lahir'    => 'nullable|date',
            'usia'             => 'nullable|integer',
            'jenis_kelamin'    => 'nullable|string|max:50',
            'status_kawin'     => 'nullable|string|max:100',
            'nomor_bpjs_kis'   => 'nullable|string|max:100',
            'kode_faskes_tk_1' => 'nullable|string|max:100',
            'nama_faskes_tk_1' => 'nullable|string|max:255',
            'alamat'           => 'nullable|string',
            'catatan'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $family->update($validator->validated());

        return response()->json($family);
    }

    public function destroyFamily(int $id): JsonResponse
    {
        $family = EmployeeFamily::findOrFail($id);
        $family->delete();

        return response()->json(['message' => 'Data anggota keluarga berhasil dihapus']);
    }

    // ============================
    // CONTRACT HISTORY
    // ============================

    public function addContract(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tanggal_mulai'      => 'required|date',
            'tanggal_selesai'    => 'required|date|after:tanggal_mulai',
            'masa_kontrak_bulan' => 'required|integer|min:1',
            'catatan'            => 'nullable|string',
            'sk_file'            => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        $lastKontrak = $employee->contractHistories()->max('kontrak_ke') ?? 0;
        $data['kontrak_ke'] = $lastKontrak + 1;
        $data['employee_id'] = $employee->id;

        if ($request->hasFile('sk_file')) {
            $data['sk_path'] = $request->file('sk_file')->store('contract-sk', 'public');
        }
        unset($data['sk_file']);

        $history = ContractHistory::create($data);

        $employee->update([
            'kontrak'  => 'Kontrak ' . $data['kontrak_ke'],
            'outtoday' => $data['tanggal_selesai'],
        ]);

        return response()->json($history, 201);
    }

    public function deleteContract(int $id): JsonResponse
    {
        $history = ContractHistory::findOrFail($id);

        if ($history->sk_path) {
            Storage::disk('public')->delete($history->sk_path);
        }

        $history->delete();

        return response()->json(['message' => 'Riwayat kontrak berhasil dihapus']);
    }

    // ============================
    // SK FILE DOWNLOAD
    // ============================

    public function downloadSk(string $filename)
    {
        foreach (['employee-sk', 'contract-sk'] as $dir) {
            $path = "{$dir}/{$filename}";
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->download($path, $filename);
            }
        }

        abort(404, 'File SK tidak ditemukan');
    }

    // ============================
    // DEPARTMENTS CRUD
    // ============================

    public function departments(): JsonResponse
    {
        return response()->json(Department::orderBy('name')->get());
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:departments,name',
        ]);

        $dept = Department::create(['name' => $request->name]);
        return response()->json($dept, 201);
    }

    public function updateDepartment(Request $request, int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);
        $request->validate([
            'name' => "required|string|max:255|unique:departments,name,{$id}",
        ]);

        $dept->update(['name' => $request->name]);
        return response()->json($dept);
    }

    public function destroyDepartment(int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);
        $dept->delete();
        return response()->json(['message' => 'Departemen berhasil dihapus']);
    }
}
