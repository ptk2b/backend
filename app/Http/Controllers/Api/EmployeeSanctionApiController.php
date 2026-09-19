<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSanction;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EmployeeSanctionApiController extends Controller
{
    /**
     * Standard Area Kerja list matching corporate mining reference.
     */
    const STANDARD_AREAS = [
        'ADMINISTRASI',
        'SM-D PRODUKSI',
        'SM-C PRODUKSI',
        'PAKU/COAL PRODUKSI',
        'OVER BURDEN PRODUKSI',
        'OFFICE',
        'MEKANIK - ELECTRIC - WELDER - TYRE',
        'LOGISTIC - FUEL - CARPENTER',
        'SHE',
    ];

    /**
     * Standard violation rows for monitoring matrix.
     */
    const MATRIX_STRUCTURE = [
        ['jenis' => 'FATIGUE', 'sanksi' => 'SP3', 'label' => 'FATIGUE - SP3'],
        ['jenis' => 'INCAR', 'sanksi' => 'SP1', 'label' => 'INCAR - SP1'],
        ['jenis' => 'INCAR', 'sanksi' => 'SP2', 'label' => 'INCAR - SP2'],
        ['jenis' => 'INCAR', 'sanksi' => 'SP3', 'label' => 'INCAR - SP3'],
        ['jenis' => 'APD', 'sanksi' => 'SP1', 'label' => 'APD - SP1'],
        ['jenis' => 'APD', 'sanksi' => 'SP2', 'label' => 'APD - SP2'],
        ['jenis' => 'APD', 'sanksi' => 'SP3', 'label' => 'APD - SP3'],
        ['jenis' => 'ABSENSI', 'sanksi' => 'SP1', 'label' => 'ABSENSI - SP1'],
        ['jenis' => 'ABSENSI', 'sanksi' => 'SP2', 'label' => 'ABSENSI - SP2'],
        ['jenis' => 'ABSENSI', 'sanksi' => 'SP3', 'label' => 'ABSENSI - SP3'],
        ['jenis' => 'LAINNYA', 'sanksi' => 'SP1', 'label' => 'LAINNYA - SP1'],
        ['jenis' => 'LAINNYA', 'sanksi' => 'SP2', 'label' => 'LAINNYA - SP2'],
        ['jenis' => 'LAINNYA', 'sanksi' => 'SP3', 'label' => 'LAINNYA - SP3'],
        ['jenis' => 'LAINNYA', 'sanksi' => 'PHK_PENSIUN', 'label' => '(PHK) PENSIUN'],
        ['jenis' => 'LAINNYA', 'sanksi' => 'PHK_RESIGN', 'label' => '(PHK) RESIGN'],
        ['jenis' => 'LAINNYA', 'sanksi' => 'PHK', 'label' => 'PHK'],
    ];

    /**
     * List sanctions with multi filters, search, and KPI summaries.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EmployeeSanction::with(['employee:id,nip,nik,nama_lengkap,jabatan,departemen,status_karyawan,status_hubungan_kerja', 'creator:id,name,username']);

        // Filter by Year
        if ($year = $request->input('year')) {
            $query->whereYear('tanggal_sp', $year);
        }

        // Filter by Month
        if ($month = $request->input('month')) {
            $query->whereMonth('tanggal_sp', $month);
        }

        // Filter by Departemen / Area Kerja
        if ($dept = $request->input('departemen')) {
            $query->where(function ($q) use ($dept) {
                $q->where('departemen', $dept)
                  ->orWhereHas('employee', function ($eq) use ($dept) {
                      $eq->where('departemen', $dept);
                  });
            });
        }

        // Filter by Jabatan
        if ($jabatan = $request->input('jabatan')) {
            $query->where(function ($q) use ($jabatan) {
                $q->where('jabatan', $jabatan)
                  ->orWhereHas('employee', function ($eq) use ($jabatan) {
                      $eq->where('jabatan', $jabatan);
                  });
            });
        }

        // Filter by Tingkat Sanksi (SP1, SP2, SP3, PHK, etc.)
        if ($sanksi = $request->input('tingkat_sanksi')) {
            $normalized = str_replace(' ', '', strtoupper($sanksi));
            $query->where(function ($q) use ($sanksi, $normalized) {
                $q->where('tingkat_sanksi', $sanksi)
                  ->orWhere('tingkat_sanksi', $normalized)
                  ->orWhere('tingkat_sanksi', 'like', "%{$normalized}%");
            });
        }

        // Filter by Jenis Pelanggaran
        if ($pelanggaran = $request->input('jenis_pelanggaran')) {
            $query->where('jenis_pelanggaran', strtoupper($pelanggaran));
        }

        // Filter by Status (AKTIF, EXPIRED, DICABUT, ESKALASI)
        if ($status = $request->input('status')) {
            $st = strtoupper($status);
            if ($st === 'AKTIF') {
                $query->where(function ($q) {
                    $q->where('status', 'AKTIF')
                      ->where(function ($sq) {
                          $sq->whereNull('tanggal_berakhir')
                             ->orWhere('tanggal_berakhir', '>=', Carbon::today());
                      });
                });
            } elseif ($st === 'EXPIRED') {
                $query->where(function ($q) {
                    $q->where('status', 'EXPIRED')
                      ->orWhere(function ($sq) {
                          $sq->where('status', 'AKTIF')
                             ->whereNotNull('tanggal_berakhir')
                             ->where('tanggal_berakhir', '<', Carbon::today());
                      });
                });
            } elseif ($st === 'DICABUT' || $st === 'ESKALASI') {
                $query->where('status', $st);
            }
        }

        // Filter by Employee Status (ACTIVE / NON ACTIVE)
        if ($empStatus = $request->input('employee_status', $request->input('status_karyawan'))) {
            $query->whereHas('employee', function ($eq) use ($empStatus) {
                $eq->where('status_karyawan', strtoupper($empStatus));
            });
        }

        // Search text (employee name, NIP, NIK, nomor surat)
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nomor_surat', 'like', "%{$search}%")
                  ->orWhere('alasan_pelanggaran', 'like', "%{$search}%")
                  ->orWhere('kronologi', 'like', "%{$search}%")
                  ->orWhereHas('employee', function ($eq) use ($search) {
                      $eq->where('nama_lengkap', 'like', "%{$search}%")
                         ->orWhere('nip', 'like', "%{$search}%")
                         ->orWhere('nik', 'like', "%{$search}%");
                  });
            });
        }

        // Calculate KPI Statistics
        $today = Carbon::today();
        $currentYear = $request->input('year', $today->year);

        $stats = [
            'total_active_sp1' => EmployeeSanction::whereIn('tingkat_sanksi', ['SP1', 'SP 1'])
                ->where('status', '!=', 'DICABUT')
                ->where('status', '!=', 'ESKALASI')
                ->where(function ($q) use ($today) {
                    $q->whereNull('tanggal_berakhir')->orWhere('tanggal_berakhir', '>=', $today);
                })->count(),

            'total_active_sp2' => EmployeeSanction::whereIn('tingkat_sanksi', ['SP2', 'SP 2'])
                ->where('status', '!=', 'DICABUT')
                ->where('status', '!=', 'ESKALASI')
                ->where(function ($q) use ($today) {
                    $q->whereNull('tanggal_berakhir')->orWhere('tanggal_berakhir', '>=', $today);
                })->count(),

            'total_active_sp3' => EmployeeSanction::whereIn('tingkat_sanksi', ['SP3', 'SP 3'])
                ->where('status', '!=', 'DICABUT')
                ->where('status', '!=', 'ESKALASI')
                ->where(function ($q) use ($today) {
                    $q->whereNull('tanggal_berakhir')->orWhere('tanggal_berakhir', '>=', $today);
                })->count(),

            'total_expiring_soon' => EmployeeSanction::whereIn('tingkat_sanksi', ['SP1', 'SP2', 'SP3', 'SP 1', 'SP 2', 'SP 3'])
                ->where('status', '!=', 'DICABUT')
                ->where('status', '!=', 'ESKALASI')
                ->whereNotNull('tanggal_berakhir')
                ->whereBetween('tanggal_berakhir', [$today, $today->copy()->addDays(30)])
                ->count(),

            'total_phk_year' => EmployeeSanction::whereIn('tingkat_sanksi', ['PHK', 'PHK_PENSIUN', 'PHK_RESIGN', 'PHK SANKSI'])
                ->whereYear('tanggal_sp', $currentYear)
                ->count(),

            'total_violations_year' => EmployeeSanction::whereYear('tanggal_sp', $currentYear)->count(),
        ];

        $stats['total_active_all'] = $stats['total_active_sp1'] + $stats['total_active_sp2'] + $stats['total_active_sp3'];

        $perPage = (int) $request->input('per_page', 25);
        $data = $perPage > 0 
            ? $query->orderBy('tanggal_sp', 'desc')->orderBy('id', 'desc')->paginate($perPage)
            : $query->orderBy('tanggal_sp', 'desc')->orderBy('id', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'stats'  => $stats,
            'data'   => $data,
        ]);
    }

    /**
     * Summary Matrix matching Excel format (Departemen x Jenis Pelanggaran x Sanksi x Bulan).
     */
    public function summaryMatrix(Request $request): JsonResponse
    {
        $year = (int) $request->input('year', Carbon::now()->year);
        $filterDept = $request->input('departemen');

        // Fetch all distinct departments from employees and sanctions
        $dbDepts = Employee::whereNotNull('departemen')
            ->where('departemen', '!=', '')
            ->distinct()
            ->pluck('departemen')
            ->toArray();

        $sanctionDepts = EmployeeSanction::whereNotNull('departemen')
            ->where('departemen', '!=', '')
            ->distinct()
            ->pluck('departemen')
            ->toArray();

        // Combine standard areas + DB departments
        $allDepts = array_values(array_unique(array_merge(self::STANDARD_AREAS, $dbDepts, $sanctionDepts)));

        if ($filterDept) {
            $allDepts = array_values(array_filter($allDepts, fn($d) => strcasecmp($d, $filterDept) === 0));
        }

        // Query all sanctions for the selected year
        $records = EmployeeSanction::whereYear('tanggal_sp', $year)
            ->get(['id', 'departemen', 'jenis_pelanggaran', 'tingkat_sanksi', 'tanggal_sp']);

        // Month abbreviations
        $months = ['JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGS', 'SEP', 'OKT', 'NOP', 'DES'];

        $matrix = [];
        $grandTotalsByMonth = array_fill(1, 12, 0);
        $grandTotalOverall = 0;

        foreach ($allDepts as $deptName) {
            $deptRows = [];
            $deptMonthlyTotals = array_fill(1, 12, 0);
            $deptOverallTotal = 0;

            foreach (self::MATRIX_STRUCTURE as $rowDef) {
                $jenis = $rowDef['jenis'];
                $sanksi = $rowDef['sanksi'];
                $label = $rowDef['label'];

                // Filter records for this row
                $matched = $records->filter(function ($item) use ($deptName, $jenis, $sanksi) {
                    $itemDept = trim($item->departemen ?? '');
                    if (strcasecmp($itemDept, trim($deptName)) !== 0) {
                        return false;
                    }

                    $itemJenis = strtoupper(trim($item->jenis_pelanggaran ?? ''));
                    $itemSanksi = str_replace(' ', '', strtoupper(trim($item->tingkat_sanksi ?? '')));
                    $targetSanksi = str_replace(' ', '', strtoupper($sanksi));

                    // Check sanksi match
                    $sanksiMatch = ($itemSanksi === $targetSanksi)
                        || ($targetSanksi === 'PHK' && in_array($itemSanksi, ['PHK', 'PHKSANKSI']));

                    if (!$sanksiMatch) return false;

                    // For fatigue, it's strictly FATIGUE
                    if ($jenis === 'FATIGUE') return $itemJenis === 'FATIGUE';

                    // For PHK rows, ignore specific violation category if already tagged as PHK
                    if (in_array($targetSanksi, ['PHK_PENSIUN', 'PHK_RESIGN', 'PHK'])) {
                        return true;
                    }

                    return $itemJenis === $jenis;
                });

                // Calculate counts per month (1 to 12)
                $monthlyCounts = [];
                $rowTotal = 0;
                for ($m = 1; $m <= 12; $m++) {
                    $c = $matched->filter(function ($r) use ($m) {
                        return (int) Carbon::parse($r->tanggal_sp)->month === $m;
                    })->count();

                    $monthlyCounts[$m] = $c;
                    $rowTotal += $c;
                    $deptMonthlyTotals[$m] += $c;
                    $grandTotalsByMonth[$m] += $c;
                }

                $deptOverallTotal += $rowTotal;
                $grandTotalOverall += $rowTotal;

                $deptRows[] = [
                    'jenis_pelanggaran' => $jenis,
                    'sanksi'            => $sanksi,
                    'label'             => $label,
                    'monthly_counts'    => $monthlyCounts,
                    'total'             => $rowTotal,
                ];
            }

            $matrix[] = [
                'departemen'      => $deptName,
                'rows'            => $deptRows,
                'monthly_totals'  => $deptMonthlyTotals,
                'total'           => $deptOverallTotal,
            ];
        }

        return response()->json([
            'status'             => 'success',
            'year'               => $year,
            'months'             => $months,
            'matrix'             => $matrix,
            'grand_totals_month' => $grandTotalsByMonth,
            'grand_total_all'    => $grandTotalOverall,
        ]);
    }

    /**
     * Check existing active warnings for a selected employee to provide smart escalation recommendation.
     */
    public function activeWarningsByEmployee(int $employeeId): JsonResponse
    {
        $employee = Employee::findOrFail($employeeId);

        $activeSanctions = EmployeeSanction::where('employee_id', $employeeId)
            ->where('status', '!=', 'DICABUT')
            ->where('status', '!=', 'ESKALASI')
            ->where(function ($q) {
                $q->whereNull('tanggal_berakhir')
                  ->orWhere('tanggal_berakhir', '>=', Carbon::today());
            })
            ->orderBy('tanggal_sp', 'desc')
            ->get();

        $highestLevel = null;
        $recommendedNext = 'SP1';

        foreach ($activeSanctions as $s) {
            $norm = str_replace(' ', '', strtoupper($s->tingkat_sanksi));
            if ($norm === 'SP3' || $norm === 'PHK') {
                $highestLevel = 'SP3';
                $recommendedNext = 'PHK';
                break;
            } elseif ($norm === 'SP2' && $highestLevel !== 'SP3') {
                $highestLevel = 'SP2';
                $recommendedNext = 'SP3';
            } elseif ($norm === 'SP1' && !$highestLevel) {
                $highestLevel = 'SP1';
                $recommendedNext = 'SP2';
            }
        }

        return response()->json([
            'status'                 => 'success',
            'employee'               => [
                'id'           => $employee->id,
                'nama_lengkap' => $employee->nama_lengkap,
                'nip'          => $employee->nip,
                'nik'          => $employee->nik,
                'departemen'   => $employee->departemen,
                'jabatan'      => $employee->jabatan,
            ],
            'has_active_sanction'    => $activeSanctions->isNotEmpty(),
            'active_count'           => $activeSanctions->count(),
            'highest_level'          => $highestLevel,
            'recommended_next_level' => $recommendedNext,
            'active_sanctions'       => $activeSanctions,
        ]);
    }

    /**
     * Store new SP / Sanction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'employee_id'         => 'required|exists:employees,id',
            'nomor_surat'         => 'nullable|string|max:255',
            'tingkat_sanksi'      => 'required|string|max:50',
            'jenis_pelanggaran'   => 'required|string|max:50',
            'departemen'          => 'nullable|string|max:255',
            'jabatan'             => 'nullable|string|max:255',
            'tanggal_sp'          => 'required|date',
            'tanggal_berakhir'    => 'nullable|date',
            'alasan_pelanggaran'  => 'nullable|string',
            'kronologi'           => 'nullable|string',
            'file_sp'             => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'catatan'             => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        // Default departemen & jabatan from employee if not provided
        $dept = !empty($validated['departemen']) ? trim($validated['departemen']) : $employee->departemen;
        $jabatan = !empty($validated['jabatan']) ? trim($validated['jabatan']) : $employee->jabatan;

        // Auto calculate +6 months if SP and tanggal_berakhir is empty
        $tglSp = Carbon::parse($validated['tanggal_sp']);
        $tglBerakhir = !empty($validated['tanggal_berakhir'])
            ? Carbon::parse($validated['tanggal_berakhir'])
            : null;

        $tingkatNorm = str_replace(' ', '', strtoupper($validated['tingkat_sanksi']));

        if (!$tglBerakhir && in_array($tingkatNorm, ['SP1', 'SP2', 'SP3'])) {
            $tglBerakhir = $tglSp->copy()->addMonths(6)->subDay();
        }

        // Handle file upload
        $filePath = null;
        if ($request->hasFile('file_sp')) {
            $file = $request->file('file_sp');
            $filename = 'SP_' . $employee->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('sanctions', $filename, 'public');
        }

        // Create Sanction
        $sanction = EmployeeSanction::create([
            'employee_id'        => $employee->id,
            'nomor_surat'        => $validated['nomor_surat'] ?? null,
            'tingkat_sanksi'     => strtoupper(trim($validated['tingkat_sanksi'])),
            'jenis_pelanggaran'  => strtoupper(trim($validated['jenis_pelanggaran'])),
            'departemen'         => $dept,
            'jabatan'            => $jabatan,
            'tanggal_sp'         => $tglSp->format('Y-m-d'),
            'tanggal_berakhir'   => $tglBerakhir ? $tglBerakhir->format('Y-m-d') : null,
            'alasan_pelanggaran' => $validated['alasan_pelanggaran'] ?? null,
            'kronologi'          => $validated['kronologi'] ?? null,
            'file_sp_path'       => $filePath,
            'status'             => 'AKTIF',
            'catatan'            => $validated['catatan'] ?? null,
            'created_by'         => $request->user()?->id,
        ]);

        // If sanksi is PHK, update employee status in Man Power
        if (in_array($tingkatNorm, ['PHK', 'PHK_PENSIUN', 'PHK_RESIGN', 'PHKSANKSI'])) {
            $employee->update([
                'status_karyawan' => 'NON ACTIVE',
                'outtoday'        => $tglSp->format('Y-m-d'),
                'outhal'          => strtoupper($validated['tingkat_sanksi']),
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Surat Peringatan / Sanksi berhasil diterbitkan.',
            'data'    => $sanction->load('employee:id,nip,nik,nama_lengkap,jabatan,departemen'),
        ], 201);
    }

    /**
     * Show single sanction detail.
     */
    public function show(int $id): JsonResponse
    {
        $sanction = EmployeeSanction::with([
            'employee.sanctions' => function ($q) use ($id) {
                $q->where('id', '!=', $id)->orderBy('tanggal_sp', 'desc');
            },
            'creator:id,name,username'
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data'   => $sanction,
        ]);
    }

    /**
     * Update sanction.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $sanction = EmployeeSanction::findOrFail($id);

        $validated = $request->validate([
            'nomor_surat'        => 'nullable|string|max:255',
            'tingkat_sanksi'     => 'sometimes|required|string|max:50',
            'jenis_pelanggaran'  => 'sometimes|required|string|max:50',
            'departemen'         => 'nullable|string|max:255',
            'jabatan'            => 'nullable|string|max:255',
            'tanggal_sp'         => 'sometimes|required|date',
            'tanggal_berakhir'   => 'nullable|date',
            'alasan_pelanggaran' => 'nullable|string',
            'kronologi'          => 'nullable|string',
            'status'             => 'nullable|string|in:AKTIF,EXPIRED,DICABUT,ESKALASI',
            'file_sp'            => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'catatan'            => 'nullable|string',
        ]);

        if ($request->hasFile('file_sp')) {
            if ($sanction->file_sp_path && Storage::disk('public')->exists($sanction->file_sp_path)) {
                Storage::disk('public')->delete($sanction->file_sp_path);
            }
            $file = $request->file('file_sp');
            $filename = 'SP_' . $sanction->employee_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $validated['file_sp_path'] = $file->storeAs('sanctions', $filename, 'public');
        }

        if (isset($validated['tingkat_sanksi'])) {
            $validated['tingkat_sanksi'] = strtoupper(trim($validated['tingkat_sanksi']));
        }
        if (isset($validated['jenis_pelanggaran'])) {
            $validated['jenis_pelanggaran'] = strtoupper(trim($validated['jenis_pelanggaran']));
        }

        $sanction->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Data Sanksi berhasil diperbarui.',
            'data'    => $sanction->fresh()->load('employee:id,nip,nik,nama_lengkap,jabatan,departemen'),
        ]);
    }

    /**
     * Delete sanction.
     */
    public function destroy(int $id): JsonResponse
    {
        $sanction = EmployeeSanction::findOrFail($id);

        if ($sanction->file_sp_path && Storage::disk('public')->exists($sanction->file_sp_path)) {
            Storage::disk('public')->delete($sanction->file_sp_path);
        }

        $sanction->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Data Surat Peringatan berhasil dihapus.',
        ]);
    }

    /**
     * Download or view SP document file.
     */
    public function downloadFile(int $id)
    {
        $sanction = EmployeeSanction::findOrFail($id);

        if (!$sanction->file_sp_path || !Storage::disk('public')->exists($sanction->file_sp_path)) {
            abort(404, 'Berkas fisik SP tidak ditemukan.');
        }

        $fullPath = Storage::disk('public')->path($sanction->file_sp_path);
        $filename = basename($sanction->file_sp_path);
        $mime = mime_content_type($fullPath) ?: 'application/octet-stream';

        return response()->file($fullPath, [
            'Content-Type'                => $mime,
            'Content-Disposition'         => "inline; filename=\"{$filename}\"",
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Get lookup options: all departments, all positions, and all employees (for dropdowns).
     * Only returns ACTIVE employees unless explicitly requested otherwise.
     */
    public function lookupOptions(?Request $request = null): JsonResponse
    {
        $request = $request ?? request();
        $onlyActive = !$request->boolean('include_inactive', false);

        // 1. Departments: standard areas + distinct in active employees + departments table
        $tableDepts = Department::pluck('name')->toArray();
        $deptQuery = Employee::whereNotNull('departemen')->where('departemen', '!=', '');
        if ($onlyActive) {
            $deptQuery->where('status_karyawan', 'ACTIVE');
        }
        $empDepts = $deptQuery->distinct()->pluck('departemen')->toArray();
        $allDepts = array_values(array_unique(array_filter(array_merge(self::STANDARD_AREAS, $tableDepts, $empDepts))));
        sort($allDepts, SORT_NATURAL | SORT_FLAG_CASE);

        // 2. Positions from active employees
        $posQuery = Employee::whereNotNull('jabatan')->where('jabatan', '!=', '');
        if ($onlyActive) {
            $posQuery->where('status_karyawan', 'ACTIVE');
        }
        $positions = $posQuery->distinct()->pluck('jabatan')->toArray();
        sort($positions, SORT_NATURAL | SORT_FLAG_CASE);

        // 3. Employees - ONLY ACTIVE
        $empQuery = Employee::select('id', 'nip', 'nik', 'nama_lengkap', 'jabatan', 'departemen', 'status_karyawan');
        if ($onlyActive) {
            $empQuery->where('status_karyawan', 'ACTIVE');
        }
        $employees = $empQuery->orderBy('nama_lengkap', 'asc')->get();

        return response()->json([
            'status'      => 'success',
            'departments' => $allDepts,
            'positions'   => $positions,
            'employees'   => $employees,
        ]);
    }
}
