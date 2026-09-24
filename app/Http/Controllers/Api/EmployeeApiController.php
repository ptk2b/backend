<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeFamily;
use App\Models\ContractHistory;
use App\Models\Department;
use App\Models\EmployeeSanction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Rules\SecureFile;

class EmployeeApiController extends Controller
{
    // ============================
    // EMPLOYEE CRUD
    // ============================

    /**
     * Auto normalize all PKWT contracts in database to ensure minimal 6-month duration.
     * Fixes any historical dates that prematurely flattened to today/expired date.
     */
    public function autoNormalizePkwtContracts(): void
    {
        try {
            DB::statement("
                UPDATE employees 
                SET outtoday = DATE_SUB(DATE_ADD(`in`, INTERVAL 6 MONTH), INTERVAL 1 DAY)
                WHERE status_hubungan_kerja = 'PKWT' 
                  AND `in` IS NOT NULL 
                  AND outtoday IS NOT NULL
                  AND outtoday < DATE_SUB(DATE_ADD(`in`, INTERVAL 6 MONTH), INTERVAL 1 DAY)
            ");

            DB::statement("
                UPDATE contract_histories ch
                JOIN employees e ON e.id = ch.employee_id
                SET ch.tanggal_selesai = DATE_SUB(DATE_ADD(ch.tanggal_mulai, INTERVAL 6 MONTH), INTERVAL 1 DAY),
                    ch.masa_kontrak_bulan = 6
                WHERE e.status_hubungan_kerja = 'PKWT'
                  AND ch.tanggal_mulai IS NOT NULL
                  AND ch.tanggal_selesai IS NOT NULL
                  AND ch.tanggal_selesai < DATE_SUB(DATE_ADD(ch.tanggal_mulai, INTERVAL 6 MONTH), INTERVAL 1 DAY)
            ");
        } catch (\Exception $e) {
            try {
                Employee::where('status_hubungan_kerja', 'PKWT')
                    ->whereNotNull('in')
                    ->whereNotNull('outtoday')
                    ->chunkById(100, function ($employees) {
                        foreach ($employees as $emp) {
                            try {
                                $inDate = Carbon::parse($emp->in);
                                $minEnd = $inDate->copy()->addMonths(6)->subDay();
                                if (Carbon::parse($emp->outtoday)->lt($minEnd)) {
                                    $emp->update(['outtoday' => $minEnd->format('Y-m-d')]);
                                }
                            } catch (\Exception $ex) {}
                        }
                    });
            } catch (\Exception $ex) {
                Log::warning('autoNormalizePkwtContracts error: ' . $ex->getMessage());
            }
        }
        self::flushManpowerCache();
    }

    public function normalizePkwt(): JsonResponse
    {
        $this->autoNormalizePkwtContracts();
        return response()->json(['message' => 'Semua kontrak PKWT berhasil dinormalisasi otomatis ke minimal 6 bulan.']);
    }

    /**
     * List all employees with search + multi filters + families count.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::withCount('families')
            ->with(['activeSanctions' => function ($q) {
                $q->select('id', 'employee_id', 'nomor_surat', 'tingkat_sanksi', 'jenis_pelanggaran', 'tanggal_sp', 'tanggal_berakhir', 'status');
            }]);

        if ($request->boolean('with_relations')) {
            $query->with(['families', 'contractHistories', 'sanctions']);
        }

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
            if (strtoupper($lokal) === 'LOKAL') {
                $query->where(function ($q) {
                    $q->where('lokal_nonlokal', 'like', '%LOKAL%')
                      ->where('lokal_nonlokal', 'not like', '%NON%');
                });
            } elseif (str_contains(strtoupper($lokal), 'NON')) {
                $query->where(function ($q) {
                    $q->where('lokal_nonlokal', 'like', '%NON%');
                });
            } else {
                $query->where('lokal_nonlokal', $lokal);
            }
        }

        // Filter by jenis_kelamin
        if ($gender = $request->input('jenis_kelamin')) {
            $gUpper = strtoupper($gender);
            if ($gUpper === 'LAKI-LAKI' || str_contains($gUpper, 'LAKI') || $gUpper === '1') {
                $query->where(function ($q) {
                    $q->where('jenis_kelamin', 'like', '%LAKI%')
                      ->orWhere('jenis_kelamin', '1')
                      ->orWhere('jenis_kelamin', 'L');
                });
            } elseif ($gUpper === 'PEREMPUAN' || str_contains($gUpper, 'PEREMPUAN') || $gUpper === '2') {
                $query->where(function ($q) {
                    $q->where('jenis_kelamin', 'like', '%PEREMPUAN%')
                      ->orWhere('jenis_kelamin', '2')
                      ->orWhere('jenis_kelamin', 'P');
                });
            } else {
                $query->where('jenis_kelamin', $gender);
            }
        }

        // Filter by pendidikan_terakhir
        if ($edu = $request->input('pendidikan_terakhir')) {
            $this->applyEducationFilter($query, $edu);
        }

        // Filter by masa_kerja (calculated between `in` and `outtoday` or CURDATE())
        if ($mk = $request->input('filter_masa_kerja')) {
            $dateExpr = "TIMESTAMPDIFF(MONTH, `in`, IF(status_karyawan = 'NON ACTIVE' AND outtoday IS NOT NULL, outtoday, CURDATE()))";
            if ($mk === '<1' || $mk === '< 1 Tahun') {
                $query->whereNotNull('in')->whereRaw("{$dateExpr} < 12");
            } elseif ($mk === '1-3' || $mk === '1 - 3 Tahun') {
                $query->whereNotNull('in')->whereRaw("{$dateExpr} >= 12 AND {$dateExpr} < 36");
            } elseif ($mk === '3-5' || $mk === '3 - 5 Tahun') {
                $query->whereNotNull('in')->whereRaw("{$dateExpr} >= 36 AND {$dateExpr} < 60");
            } elseif ($mk === '5-10') {
                $query->whereNotNull('in')->whereRaw("{$dateExpr} >= 60 AND {$dateExpr} < 120");
            } elseif ($mk === '>10' || $mk === '> 5 Tahun') {
                $query->whereNotNull('in')->whereRaw("{$dateExpr} >= 120");
            }
        }

        // Dynamic Sorting & Ranking
        $sortAge = $request->input('sort_age');
        $sortMasaKerja = $request->input('sort_masa_kerja');
        $sortBy = $request->input('sort_by');
        $sortDir = strtolower($request->input('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        if ($sortAge === 'oldest' || ($sortBy === 'usia' && $sortDir === 'desc')) {
            // Oldest first: earliest birth date or highest age, nulls at the end
            $query->orderByRaw("CASE 
                WHEN tanggal_lahir IS NOT NULL AND tanggal_lahir != '0000-00-00' THEN tanggal_lahir 
                WHEN usia IS NOT NULL AND usia > 0 THEN DATE_SUB(CURDATE(), INTERVAL usia YEAR) 
                ELSE '9999-12-31' 
            END ASC");
        } elseif ($sortAge === 'youngest' || ($sortBy === 'usia' && $sortDir === 'asc')) {
            // Youngest first: latest birth date or lowest age, nulls at the end
            $query->orderByRaw("CASE 
                WHEN tanggal_lahir IS NOT NULL AND tanggal_lahir != '0000-00-00' THEN tanggal_lahir 
                WHEN usia IS NOT NULL AND usia > 0 THEN DATE_SUB(CURDATE(), INTERVAL usia YEAR) 
                ELSE '1000-01-01' 
            END DESC");
        } elseif ($sortMasaKerja === 'longest' || ($sortBy === 'masa_kerja' && $sortDir === 'desc')) {
            // Longest masa kerja first, nulls at the end
            $dateExpr = "TIMESTAMPDIFF(MONTH, `in`, IF(status_karyawan = 'NON ACTIVE' AND outtoday IS NOT NULL, outtoday, CURDATE()))";
            $query->orderByRaw("CASE WHEN `in` IS NOT NULL THEN {$dateExpr} ELSE -1 END DESC");
        } elseif ($sortMasaKerja === 'shortest' || ($sortBy === 'masa_kerja' && $sortDir === 'asc')) {
            // Shortest masa kerja first, nulls at the end
            $dateExpr = "TIMESTAMPDIFF(MONTH, `in`, IF(status_karyawan = 'NON ACTIVE' AND outtoday IS NOT NULL, outtoday, CURDATE()))";
            $query->orderByRaw("CASE WHEN `in` IS NOT NULL THEN {$dateExpr} ELSE 999999 END ASC");
        } else {
            $allowedSorts = [
                'nama_lengkap'          => 'nama_lengkap',
                'nip'                   => 'nip',
                'nik'                   => 'nik',
                'jabatan'               => 'jabatan',
                'departemen'            => 'departemen',
                'usia'                  => 'usia',
                'tanggal_lahir'         => 'tanggal_lahir',
                'in'                    => 'in',
                'outtoday'              => 'outtoday',
                'status_karyawan'       => 'status_karyawan',
                'status_hubungan_kerja' => 'status_hubungan_kerja',
                'created_at'            => 'created_at',
            ];
            $sortColumn = $allowedSorts[$sortBy ?? 'nama_lengkap'] ?? 'nama_lengkap';
            $query->orderBy($sortColumn, $sortDir);
        }

        if ($request->boolean('all') || $request->input('per_page') === 'all' || (string) $request->input('per_page') === '0') {
            $employees = $query->get();
            return response()->json($employees);
        }

        $perPage = (int) $request->input('per_page', 50);
        if ($perPage <= 0) {
            $perPage = 50;
        } elseif ($perPage > 5000) {
            $perPage = 5000;
        }

        $paginated = $query->paginate($perPage);

        return response()->json($paginated);
    }

    /**
     * Apply simplified education tier filter (SD, SMP, SMA/SMK, D3, S1, S2, S3, BELUM TERISI).
     */
    private function applyEducationFilter($query, string $edu): void
    {
        $eduUpper = strtoupper(trim($edu));

        if ($eduUpper === 'BELUM TERISI' || $eduUpper === 'EMPTY' || $eduUpper === 'NULL') {
            $query->where(function ($q) {
                $q->whereNull('pendidikan_terakhir')
                  ->orWhere('pendidikan_terakhir', '')
                  ->orWhere('pendidikan_terakhir', '-');
            });
        } elseif ($eduUpper === 'SD') {
            $query->where(function ($q) {
                $q->where('pendidikan_terakhir', 'like', '%SD%')
                  ->orWhere('pendidikan_terakhir', 'like', '%SEKOLAH DASAR%')
                  ->orWhere('pendidikan_terakhir', 'like', '%PAKET A%')
                  ->orWhere('pendidikan_terakhir', 'like', '%IBTIDAIYAH%');
            });
        } elseif ($eduUpper === 'SMP') {
            $query->where(function ($q) {
                $q->where('pendidikan_terakhir', 'like', '%SMP%')
                  ->orWhere('pendidikan_terakhir', 'like', '%SLTP%')
                  ->orWhere('pendidikan_terakhir', 'like', '%MTS%')
                  ->orWhere('pendidikan_terakhir', 'like', '%PAKET B%')
                  ->orWhere('pendidikan_terakhir', 'like', '%TSANAWIYAH%');
            });
        } elseif ($eduUpper === 'SMA/SMK' || $eduUpper === 'SMA' || $eduUpper === 'SMK' || $eduUpper === 'SLTA') {
            $query->where(function ($q) {
                $q->where('pendidikan_terakhir', 'like', '%SMA%')
                  ->orWhere('pendidikan_terakhir', 'like', '%SMK%')
                  ->orWhere('pendidikan_terakhir', 'like', '%SLTA%')
                  ->orWhere('pendidikan_terakhir', 'like', '%STM%')
                  ->orWhere('pendidikan_terakhir', 'like', '%SMEA%')
                  ->orWhere('pendidikan_terakhir', 'like', '%SMU%')
                  ->orWhere('pendidikan_terakhir', 'like', '%PAKET C%')
                  ->orWhere('pendidikan_terakhir', 'like', '%ALIYAH%')
                  ->orWhere('pendidikan_terakhir', 'like', '%KEJURUAN%');
            });
        } elseif ($eduUpper === 'D3' || $eduUpper === 'D1' || $eduUpper === 'D2' || $eduUpper === 'DIPLOMA') {
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('pendidikan_terakhir', 'like', '%DIPLOMA III%')
                        ->orWhere('pendidikan_terakhir', 'like', '%DIPLOMA 3%')
                        ->orWhere('pendidikan_terakhir', 'like', '%DIPLOMA I%')
                        ->orWhere('pendidikan_terakhir', 'like', '%DIPLOMA II%')
                        ->orWhere('pendidikan_terakhir', 'like', '%DIPLOMA 1%')
                        ->orWhere('pendidikan_terakhir', 'like', '%DIPLOMA 2%')
                        ->orWhere('pendidikan_terakhir', 'like', '%D3%')
                        ->orWhere('pendidikan_terakhir', 'like', '%D-3%')
                        ->orWhere('pendidikan_terakhir', 'like', '%D2%')
                        ->orWhere('pendidikan_terakhir', 'like', '%D1%')
                        ->orWhere('pendidikan_terakhir', 'like', '%AKADEMI%')
                        ->orWhere('pendidikan_terakhir', 'like', '%SARJANA MUDA%')
                        ->orWhere('pendidikan_terakhir', 'like', '%A.MD%');
                })->where('pendidikan_terakhir', 'not like', '%DIPLOMA IV%')
                  ->where('pendidikan_terakhir', 'not like', '%STRATA I%')
                  ->where('pendidikan_terakhir', 'not like', '%STRATA 1%');
            });
        } elseif ($eduUpper === 'S1' || $eduUpper === 'D4' || $eduUpper === 'STRATA 1' || $eduUpper === 'STRATA I') {
            $query->where(function ($q) {
                $q->where(function ($sub) {
                    $sub->where('pendidikan_terakhir', 'like', '%STRATA I%')
                        ->orWhere('pendidikan_terakhir', 'like', '%STRATA 1%')
                        ->orWhere('pendidikan_terakhir', 'like', '%DIPLOMA IV%')
                        ->orWhere('pendidikan_terakhir', 'like', '%D4%')
                        ->orWhere('pendidikan_terakhir', 'like', '%D-IV%')
                        ->orWhere('pendidikan_terakhir', 'like', '%S1%')
                        ->orWhere('pendidikan_terakhir', 'like', '%S-1%')
                        ->orWhere(function ($s) {
                            $s->where('pendidikan_terakhir', 'like', '%SARJANA%')
                              ->where('pendidikan_terakhir', 'not like', '%SARJANA MUDA%');
                        });
                })->where('pendidikan_terakhir', 'not like', '%STRATA II%')
                  ->where('pendidikan_terakhir', 'not like', '%STRATA III%');
            });
        } elseif ($eduUpper === 'S2' || $eduUpper === 'STRATA 2' || $eduUpper === 'STRATA II') {
            $query->where(function ($q) {
                $q->where('pendidikan_terakhir', 'like', '%STRATA II%')
                  ->orWhere('pendidikan_terakhir', 'like', '%STRATA 2%')
                  ->orWhere('pendidikan_terakhir', 'like', '%S2%')
                  ->orWhere('pendidikan_terakhir', 'like', '%S-2%')
                  ->orWhere('pendidikan_terakhir', 'like', '%MAGISTER%')
                  ->orWhere('pendidikan_terakhir', 'like', '%MASTER%');
            });
        } elseif ($eduUpper === 'S3' || $eduUpper === 'STRATA 3' || $eduUpper === 'STRATA III') {
            $query->where(function ($q) {
                $q->where('pendidikan_terakhir', 'like', '%STRATA III%')
                  ->orWhere('pendidikan_terakhir', 'like', '%STRATA 3%')
                  ->orWhere('pendidikan_terakhir', 'like', '%S3%')
                  ->orWhere('pendidikan_terakhir', 'like', '%S-3%')
                  ->orWhere('pendidikan_terakhir', 'like', '%DOKTOR%')
                  ->orWhere('pendidikan_terakhir', 'like', '%DOCTOR%');
            });
        } else {
            $query->where('pendidikan_terakhir', 'like', "%{$edu}%");
        }
    }

    /**
     * Flush all manpower cached data (stats, bootstrap, positions).
     */
    public static function flushManpowerCache(): void
    {
        try {
            Cache::forget('manpower_bootstrap');
            Cache::forget('manpower_stats');
            Cache::forget('manpower_positions');
        } catch (\Exception $e) {
            Log::warning('Failed to flush manpower cache: ' . $e->getMessage());
        }
    }

    /**
     * Get global employee statistics for stat cards with 5-minute cache.
     */
    public function stats(): JsonResponse
    {
        $stats = Cache::remember('manpower_stats', 300, function () {
            // Single efficient query using conditional aggregation instead of 15+ separate queries
            $row = DB::selectOne("
                SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status_karyawan = 'NON ACTIVE' THEN 1 ELSE 0 END) AS non_active,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND status_hubungan_kerja = 'PKWT' THEN 1 ELSE 0 END) AS pkwt,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND status_hubungan_kerja = 'PKWTT' THEN 1 ELSE 0 END) AS pkwtt,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND status_hubungan_kerja = 'SKPKT' THEN 1 ELSE 0 END) AS skpkt,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND lokal_nonlokal LIKE '%LOKAL%' AND lokal_nonlokal NOT LIKE '%NON%' THEN 1 ELSE 0 END) AS lokal,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND lokal_nonlokal LIKE '%NON%' THEN 1 ELSE 0 END) AS non_lokal,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND (jenis_kelamin LIKE '%LAKI%' OR jenis_kelamin = '1' OR jenis_kelamin = 'L') THEN 1 ELSE 0 END) AS laki_laki,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND (jenis_kelamin LIKE '%PEREMPUAN%' OR jenis_kelamin = '2' OR jenis_kelamin = 'P') THEN 1 ELSE 0 END) AS perempuan,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND (pendidikan_terakhir IS NULL OR pendidikan_terakhir = '' OR pendidikan_terakhir = '-') THEN 1 ELSE 0 END) AS pendidikan_kosong,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND (pendidikan_terakhir LIKE '%SD%' OR pendidikan_terakhir LIKE '%SEKOLAH DASAR%' OR pendidikan_terakhir LIKE '%PAKET A%' OR pendidikan_terakhir LIKE '%IBTIDAIYAH%') THEN 1 ELSE 0 END) AS edu_sd,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND (pendidikan_terakhir LIKE '%SMP%' OR pendidikan_terakhir LIKE '%SLTP%' OR pendidikan_terakhir LIKE '%MTS%' OR pendidikan_terakhir LIKE '%PAKET B%' OR pendidikan_terakhir LIKE '%TSANAWIYAH%') THEN 1 ELSE 0 END) AS edu_smp,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND (pendidikan_terakhir LIKE '%SMA%' OR pendidikan_terakhir LIKE '%SMK%' OR pendidikan_terakhir LIKE '%SLTA%' OR pendidikan_terakhir LIKE '%STM%' OR pendidikan_terakhir LIKE '%SMEA%' OR pendidikan_terakhir LIKE '%SMU%' OR pendidikan_terakhir LIKE '%PAKET C%' OR pendidikan_terakhir LIKE '%ALIYAH%' OR pendidikan_terakhir LIKE '%KEJURUAN%') THEN 1 ELSE 0 END) AS edu_sma,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND (pendidikan_terakhir LIKE '%D3%' OR pendidikan_terakhir LIKE '%D-3%' OR pendidikan_terakhir LIKE '%DIPLOMA III%' OR pendidikan_terakhir LIKE '%DIPLOMA 3%' OR pendidikan_terakhir LIKE '%AKADEMI%' OR pendidikan_terakhir LIKE '%SARJANA MUDA%' OR pendidikan_terakhir LIKE '%A.MD%') THEN 1 ELSE 0 END) AS edu_d3,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND (pendidikan_terakhir LIKE '%S1%' OR pendidikan_terakhir LIKE '%S-1%' OR pendidikan_terakhir LIKE '%STRATA I%' OR pendidikan_terakhir LIKE '%STRATA 1%' OR pendidikan_terakhir LIKE '%SARJANA%' OR pendidikan_terakhir LIKE '%D4%' OR pendidikan_terakhir LIKE '%DIPLOMA IV%') AND pendidikan_terakhir NOT LIKE '%SARJANA MUDA%' THEN 1 ELSE 0 END) AS edu_s1,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND (pendidikan_terakhir LIKE '%S2%' OR pendidikan_terakhir LIKE '%S-2%' OR pendidikan_terakhir LIKE '%STRATA II%' OR pendidikan_terakhir LIKE '%STRATA 2%' OR pendidikan_terakhir LIKE '%MAGISTER%' OR pendidikan_terakhir LIKE '%MASTER%') THEN 1 ELSE 0 END) AS edu_s2,
                    SUM(CASE WHEN status_karyawan = 'ACTIVE' AND (pendidikan_terakhir LIKE '%S3%' OR pendidikan_terakhir LIKE '%S-3%' OR pendidikan_terakhir LIKE '%STRATA III%' OR pendidikan_terakhir LIKE '%STRATA 3%' OR pendidikan_terakhir LIKE '%DOKTOR%' OR pendidikan_terakhir LIKE '%DOCTOR%') THEN 1 ELSE 0 END) AS edu_s3
                FROM employees
            ");

            return [
                'total'             => (int) ($row->total ?? 0),
                'active'            => (int) ($row->active ?? 0),
                'non_active'        => (int) ($row->non_active ?? 0),
                'pkwt'              => (int) ($row->pkwt ?? 0),
                'pkwtt'             => (int) ($row->pkwtt ?? 0),
                'skpkt'             => (int) ($row->skpkt ?? 0),
                'lokal'             => (int) ($row->lokal ?? 0),
                'non_lokal'         => (int) ($row->non_lokal ?? 0),
                'laki_laki'         => (int) ($row->laki_laki ?? 0),
                'perempuan'         => (int) ($row->perempuan ?? 0),
                'pendidikan_kosong' => (int) ($row->pendidikan_kosong ?? 0),
                'edu_sd'            => (int) ($row->edu_sd ?? 0),
                'edu_smp'           => (int) ($row->edu_smp ?? 0),
                'edu_sma'           => (int) ($row->edu_sma ?? 0),
                'edu_d3'            => (int) ($row->edu_d3 ?? 0),
                'edu_s1'            => (int) ($row->edu_s1 ?? 0),
                'edu_s2'            => (int) ($row->edu_s2 ?? 0),
                'edu_s3'            => (int) ($row->edu_s3 ?? 0),
            ];
        });

        return response()->json($stats);
    }

    /**
     * Get simplified list of education tiers for dropdown filter.
     */
    public function educations(): JsonResponse
    {
        return response()->json(['SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3']);
    }

    /**
     * Get unique list of existing jabatan/positions for dropdown filter with 5-minute cache.
     */
    public function positions(): JsonResponse
    {
        $positions = Cache::remember('manpower_positions', 300, function () {
            return Employee::whereNotNull('jabatan')
                ->where('jabatan', '!=', '')
                ->distinct()
                ->pluck('jabatan')
                ->sort()
                ->values();
        });

        return response()->json($positions);
    }

    /**
     * Bootstrap endpoint: combine stats + departments + positions + educations in 1 call with 5-minute cache.
     * Reduces 4 separate API requests to 1 on page load, returning in < 10ms when cached.
     */
    public function bootstrap(): JsonResponse
    {
        $data = Cache::remember('manpower_bootstrap', 300, function () {
            // Stats
            $statsResponse = $this->stats();
            $stats = json_decode($statsResponse->getContent(), true);

            // Departments
            $departments = Department::orderBy('name')->get();

            // Positions
            $positionsResponse = $this->positions();
            $positions = json_decode($positionsResponse->getContent(), true);

            // Educations (static)
            $educations = ['SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3'];

            return [
                'stats'       => $stats,
                'departments' => $departments,
                'positions'   => $positions,
                'educations'  => $educations,
            ];
        });

        return response()->json($data);
    }

    /**
     * Show single employee with contract histories and families.
     */
    public function show($id): JsonResponse
    {
        EmployeeSanction::syncExpiredStatus();
        $employee = Employee::with(['contractHistories', 'families', 'sanctions', 'activeSanctions'])->findOrFail($id);
        if (strtoupper($employee->status_hubungan_kerja ?? '') === 'PKWT' && !empty($employee->in)) {
            try {
                $minEnd = Carbon::parse($employee->in)->addMonths(6)->subDay();
                if (empty($employee->outtoday) || Carbon::parse($employee->outtoday)->lt($minEnd)) {
                    $employee->outtoday = $minEnd->format('Y-m-d');
                    $employee->save();
                }
            } catch (\Exception $e) {}
        }
        return response()->json($employee);
    }

    /**
     * Create new employee.
     */
    public function store(Request $request): JsonResponse
    {
        $input = $request->all();
        foreach (['in', 'outtoday', 'tanggal_lahir', 'tanggal_perkawinan_perceraian', 'usia'] as $f) {
            if (isset($input[$f]) && ($input[$f] === '' || $input[$f] === 'null')) {
                $input[$f] = null;
            }
        }

        $validator = Validator::make($input, [
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
            'sk_file'                       => ['nullable', 'file', new SecureFile(['pdf', 'jpg', 'jpeg', 'png'], 10240)],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        // Enforce PKWT contract end date to at least 6 months if missing or less than 6 months
        if (($data['status_hubungan_kerja'] ?? '') === 'PKWT' && !empty($data['in'])) {
            try {
                $minEnd = Carbon::parse($data['in'])->addMonths(6)->subDay();
                if (empty($data['outtoday']) || Carbon::parse($data['outtoday'])->lt($minEnd)) {
                    $data['outtoday'] = $minEnd->format('Y-m-d');
                }
            } catch (\Exception $e) {}
        }

        if ($request->hasFile('sk_file')) {
            $data['sk_path'] = $request->file('sk_file')->store('employee-sk', 'public');
        }
        unset($data['sk_file']);

        if (!empty($data['departemen'])) {
            Department::firstOrCreate(['name' => trim($data['departemen'])]);
        }

        $employee = Employee::create($data);

        $rawContracts = $request->input('contracts');
        $hasSavedContracts = false;
        if (!empty($rawContracts)) {
            $contractsArray = is_string($rawContracts) ? json_decode($rawContracts, true) : $rawContracts;
            if (is_array($contractsArray)) {
                $hasDiserahkanCol = Schema::hasColumn('contract_histories', 'diserahkan');
                foreach ($contractsArray as $c) {
                    if (!empty($c['tanggal_mulai']) && !empty($c['tanggal_selesai'])) {
                        $kNum = intval($c['kontrak_ke'] ?? 1);
                        try {
                            $start = Carbon::parse($c['tanggal_mulai']);
                            $end = Carbon::parse($c['tanggal_selesai']);
                            $diffMonths = max(1, $start->diffInMonths($end));
                        } catch (\Exception $ex) {
                            $diffMonths = 6;
                        }

                        $cData = [
                            'tanggal_mulai'      => $c['tanggal_mulai'],
                            'tanggal_selesai'    => $c['tanggal_selesai'],
                            'masa_kontrak_bulan' => $diffMonths,
                        ];
                        if ($hasDiserahkanCol) {
                            $cData['diserahkan'] = $c['diserahkan'] ?? 'Sudah';
                        }

                        try {
                            ContractHistory::create(array_merge($cData, [
                                'employee_id' => $employee->id,
                                'kontrak_ke'  => $kNum,
                            ]));
                            $hasSavedContracts = true;
                        } catch (\Exception $ex) {
                            \Log::error("Failed saving initial contract: " . $ex->getMessage());
                        }
                    }
                }
            }
        }

        if (!$hasSavedContracts && ($data['status_hubungan_kerja'] ?? '') === 'PKWT' && !empty($data['in'])) {
            try {
                $effEnd = !empty($data['outtoday']) ? $data['outtoday'] : Carbon::parse($data['in'])->addMonths(6)->subDay()->format('Y-m-d');
                ContractHistory::firstOrCreate(
                    ['employee_id' => $employee->id, 'kontrak_ke' => 1],
                    [
                        'tanggal_mulai'      => $data['in'],
                        'tanggal_selesai'    => $effEnd,
                        'masa_kontrak_bulan' => 6,
                        'diserahkan'         => 'Sudah',
                        'catatan'            => 'Kontrak Pertama (Minimal 6 Bulan)',
                    ]
                );
            } catch (\Exception $e) {}
        }

        self::flushManpowerCache();
        return response()->json($employee->load(['contractHistories', 'families']), 201);
    }

    /**
     * Update employee.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);

        $input = $request->all();
        foreach (['in', 'outtoday', 'tanggal_lahir', 'tanggal_perkawinan_perceraian', 'usia'] as $f) {
            if (isset($input[$f]) && ($input[$f] === '' || $input[$f] === 'null')) {
                $input[$f] = null;
            }
        }

        $validator = Validator::make($input, [
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
            'domisili'                      => 'nullable|string|max:150',
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
            'nomor_telepon_rumus'           => 'nullable|string|max:50',
            'email_rumus'                   => 'nullable|string|max:255',
            'npp'                           => 'nullable|string|max:100',
            'gaji_pokok_tunjangan_tetap'    => 'nullable|string|max:100',
            'kewarganegaraan_bpjs'          => 'nullable|string|max:50',
            'sub_cabang'                    => 'nullable|string|max:100',
            'catatan'                       => 'nullable|string',
            'sk_file'                       => ['nullable', 'file', new SecureFile(['pdf', 'jpg', 'jpeg', 'png'], 10240)],
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

        $statusHub = $data['status_hubungan_kerja'] ?? $employee->status_hubungan_kerja;
        $inVal = $data['in'] ?? $employee->in;
        if ($statusHub === 'PKWT' && !empty($inVal)) {
            try {
                $minEnd = Carbon::parse($inVal)->addMonths(6)->subDay();
                if (empty($data['outtoday']) || Carbon::parse($data['outtoday'])->lt($minEnd)) {
                    $data['outtoday'] = $minEnd->format('Y-m-d');
                }
            } catch (\Exception $e) {}
        }

        $employee->update($data);

        $rawContracts = $request->input('contracts');
        if (!empty($rawContracts)) {
            $contractsArray = is_string($rawContracts) ? json_decode($rawContracts, true) : $rawContracts;
            if (is_array($contractsArray)) {
                $hasDiserahkanCol = Schema::hasColumn('contract_histories', 'diserahkan');
                foreach ($contractsArray as $c) {
                    if (!empty($c['tanggal_mulai']) && !empty($c['tanggal_selesai'])) {
                        $kNum = intval($c['kontrak_ke'] ?? 1);
                        try {
                            $start = Carbon::parse($c['tanggal_mulai']);
                            $end = Carbon::parse($c['tanggal_selesai']);
                            $diffMonths = max(1, $start->diffInMonths($end));
                        } catch (\Exception $ex) {
                            $diffMonths = 12;
                        }

                        $cData = [
                            'tanggal_mulai'      => $c['tanggal_mulai'],
                            'tanggal_selesai'    => $c['tanggal_selesai'],
                            'masa_kontrak_bulan' => $diffMonths,
                        ];
                        if ($hasDiserahkanCol) {
                            $cData['diserahkan'] = $c['diserahkan'] ?? 'Sudah';
                        }

                        try {
                            ContractHistory::updateOrCreate(
                                ['employee_id' => $employee->id, 'kontrak_ke' => $kNum],
                                $cData
                            );
                        } catch (\Exception $ex) {
                            \Log::error("Failed saving contract history: " . $ex->getMessage());
                        }
                    }
                }
            }
        }

        self::flushManpowerCache();
        return response()->json($employee->load(['contractHistories', 'families']));
    }

    /**
     * Delete employee.
     */
    public function destroy($id): JsonResponse
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
        self::flushManpowerCache();

        return response()->json(['message' => 'Data karyawan berhasil dihapus']);
    }

    /**
     * Delete ALL employees, including contracts, families, and stored files.
     */
    public function destroyAll(): JsonResponse
    {
        try {
            DB::transaction(function () {
                $employees = Employee::with('contractHistories')->get();
                foreach ($employees as $emp) {
                    if ($emp->sk_path) {
                        Storage::disk('public')->delete($emp->sk_path);
                    }
                    foreach ($emp->contractHistories as $h) {
                        if ($h->sk_path) {
                            Storage::disk('public')->delete($h->sk_path);
                        }
                    }
                }

                EmployeeFamily::query()->delete();
                ContractHistory::query()->delete();
                Employee::query()->delete();
            });

            self::flushManpowerCache();

            return response()->json([
                'success' => true,
                'message' => 'Semua data karyawan berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to delete all employees: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menghapus semua data karyawan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete selected employees by IDs.
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'Tidak ada karyawan yang dipilih'], 422);
        }

        try {
            $count = 0;
            DB::transaction(function () use ($ids, &$count) {
                $employees = Employee::with('contractHistories')->whereIn('id', $ids)->get();
                $count = $employees->count();

                foreach ($employees as $emp) {
                    if ($emp->sk_path) {
                        Storage::disk('public')->delete($emp->sk_path);
                    }
                    foreach ($emp->contractHistories as $h) {
                        if ($h->sk_path) {
                            Storage::disk('public')->delete($h->sk_path);
                        }
                    }
                }

                EmployeeFamily::whereIn('employee_id', $ids)->delete();
                ContractHistory::whereIn('employee_id', $ids)->delete();
                Employee::whereIn('id', $ids)->delete();
            });

            self::flushManpowerCache();

            return response()->json([
                'success' => true,
                'message' => "{$count} data karyawan berhasil dihapus."
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to bulk delete employees: " . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menghapus data karyawan terpilih: ' . $e->getMessage()
            ], 500);
        }
    }

    // ============================
    // EXPIRING CONTRACTS
    // ============================

    public function expiring(Request $request): JsonResponse
    {
        $this->autoNormalizePkwtContracts();
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
        $updatedEmployees = 0;
        $importedFamilies = 0;
        $updatedFamilies = 0;
        $skipped = 0;
        $errorDetails = [];

        $parseDate = function ($val) {
            if (empty($val)) return null;
            try {
                return Carbon::parse($val)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        };

        // -------------------------------------------------------------
        // STEP 1: CLASSIFY ALL ROWS (EMPLOYEE vs FAMILY)
        // -------------------------------------------------------------
        $employeeRows = []; // index => metadata
        foreach ($items as $index => $row) {
            $nama = trim($row['nama_lengkap'] ?? $row['nama'] ?? '');
            if (empty($nama)) continue;

            $nip = trim($row['nip'] ?? '');
            $jabatan = trim($row['jabatan'] ?? '');
            $inVal = trim($row['in'] ?? '');
            $pisat = strtoupper(trim($row['pisat'] ?? $row['pisat_bpjs'] ?? ''));

            $isExplicitFamily = !empty($row['is_family']) && ($row['is_family'] === true || $row['is_family'] === 'true' || $row['is_family'] === 1 || $row['is_family'] === '1');
            $isExplicitEmployee = isset($row['is_family']) && ($row['is_family'] === false || $row['is_family'] === 'false' || $row['is_family'] === 0 || $row['is_family'] === '0');

            $isPisatFamily = in_array($pisat, ['2', '3', '4', '2. SUAMI', '3. ISTRI', '4. ANAK', 'SUAMI', 'ISTRI', 'ANAK', '2 = SUAMI', '3 = ISTRI', '4 = ANAK']);

            $isEmp = false;
            if ($isExplicitEmployee) {
                $isEmp = true;
            } elseif ($isExplicitFamily || $isPisatFamily) {
                $isEmp = false;
            } elseif (!empty($nip) || !empty($jabatan) || !empty($inVal) || $pisat === '1' || str_contains($pisat, 'PESERTA')) {
                $isEmp = true;
            }

            if ($isEmp) {
                $employeeRows[$index] = [
                    'nip'          => $nip,
                    'nama'         => $nama,
                    'noKk'         => trim($row['nomor_kartu_keluarga'] ?? ''),
                    'gender'       => strtoupper(trim($row['jenis_kelamin'] ?? '')),
                    'status_kawin' => strtoupper(trim($row['status_kawin'] ?? '')),
                    'alamat'       => trim($row['alamat'] ?? ''),
                ];
            }
        }

        // Helper: Extract distinctive non-generic name tokens (length >= 4)
        $getDistinctiveTokens = function ($name) {
            $generic = [
                'MOHAMAD', 'MOHAMMAD', 'MUHAMMAD', 'MUHAMAD', 'ACHMAD', 'AHMAD',
                'ABDUL', 'SITI', 'NUR', 'BIN', 'BINTI', 'PUTRA', 'PUTRI', 'DEWI'
            ];
            $words = preg_split('/[^A-Z0-9]+/', strtoupper($name ?? ''));
            return array_values(array_filter($words, function ($w) use ($generic) {
                return strlen($w) >= 4 && !in_array($w, $generic);
            }));
        };

        // If no employees were detected (e.g. template without NIP), treat first non-empty row as employee
        if (empty($employeeRows)) {
            foreach ($items as $index => $row) {
                if (!empty($row['nama_lengkap'] ?? $row['nama'] ?? '')) {
                    $employeeRows[$index] = [
                        'nip'          => trim($row['nip'] ?? ''),
                        'nama'         => trim($row['nama_lengkap'] ?? $row['nama'] ?? ''),
                        'noKk'         => trim($row['nomor_kartu_keluarga'] ?? ''),
                        'gender'       => strtoupper(trim($row['jenis_kelamin'] ?? '')),
                        'status_kawin' => strtoupper(trim($row['status_kawin'] ?? '')),
                        'alamat'       => trim($row['alamat'] ?? ''),
                    ];
                    break;
                }
            }
        }

        // Collect child rows associated with each employee block for patronymic cross-matching
        $employeeChildren = [];
        foreach ($employeeRows as $eIdx => $eInfo) {
            $employeeChildren[$eIdx] = [];
        }
        $eIndices = array_keys($employeeRows);
        foreach ($items as $cIdx => $cRow) {
            if (isset($employeeRows[$cIdx])) continue;
            $cPisat = strtoupper(trim($cRow['pisat'] ?? $cRow['pisat_bpjs'] ?? ''));
            if ($cPisat === '4' || str_contains($cPisat, 'ANAK')) {
                $cNama = trim($cRow['nama_lengkap'] ?? $cRow['nama'] ?? '');
                if (!empty($cNama)) {
                    $prev = array_filter($eIndices, fn($k) => $k < $cIdx);
                    if (!empty($prev)) {
                        $pIdx = max($prev);
                        $employeeChildren[$pIdx][] = $cNama;
                    }
                }
            }
        }

        // -------------------------------------------------------------
        // STEP 2: DETERMINE PARENT EMPLOYEE FOR EACH FAMILY ROW
        // (Smart Lookahead & Cross-Row Child Name/Address Matching)
        // -------------------------------------------------------------
        $familyParentIndexMap = []; // index => employee_row_index
        foreach ($items as $index => $row) {
            if (isset($employeeRows[$index])) continue;

            $pisat = strtoupper(trim($row['pisat'] ?? $row['pisat_bpjs'] ?? ''));
            $gender = strtoupper(trim($row['jenis_kelamin'] ?? ''));
            $isHusband = in_array($pisat, ['2', 'SUAMI', '2. SUAMI', '2 = SUAMI']) || str_contains($pisat, 'SUAMI');
            $isWife = in_array($pisat, ['3', 'ISTRI', '3. ISTRI', '3 = ISTRI']) || str_contains($pisat, 'ISTRI');

            // A. By explicit parent_nip (with gender sanity check for husbands)
            if (!empty($row['parent_nip'])) {
                foreach ($employeeRows as $eIdx => $eInfo) {
                    if (!empty($eInfo['nip']) && strtolower($eInfo['nip']) === strtolower(trim($row['parent_nip']))) {
                        $pGender = $eInfo['gender'];
                        $pIsMale = str_contains($pGender, 'LAKI') || $pGender === '1' || $pGender === 'L';
                        // Do NOT link a husband to a male employee
                        if ($isHusband && $pIsMale) {
                            continue;
                        }
                        $parentIdx = $eIdx;
                        break;
                    }
                }
            }

            // B. By explicit parent_name (with gender sanity check for husbands)
            if ($parentIdx === null && !empty($row['parent_name'])) {
                foreach ($employeeRows as $eIdx => $eInfo) {
                    if (!empty($eInfo['nama']) && strtolower($eInfo['nama']) === strtolower(trim($row['parent_name']))) {
                        $pGender = $eInfo['gender'];
                        $pIsMale = str_contains($pGender, 'LAKI') || $pGender === '1' || $pGender === 'L';
                        if ($isHusband && $pIsMale) {
                            continue;
                        }
                        $parentIdx = $eIdx;
                        break;
                    }
                }
            }

            // C. By KK match (with strict gender compatibility check)
            $noKk = trim($row['nomor_kartu_keluarga'] ?? '');
            if ($parentIdx === null && !empty($noKk)) {
                foreach ($employeeRows as $eIdx => $eInfo) {
                    if (!empty($eInfo['noKk']) && $eInfo['noKk'] === $noKk) {
                        $pGender = $eInfo['gender'];
                        $pIsMale = str_contains($pGender, 'LAKI') || $pGender === '1' || $pGender === 'L';
                        $pIsFemale = str_contains($pGender, 'PEREMPUAN') || str_contains($pGender, 'WANITA') || $pGender === '2' || $pGender === 'P';
                        if ($isHusband && $pIsMale) continue; // Husband cannot belong to male employee
                        if ($isWife && $pIsFemale) continue;  // Wife cannot belong to female employee
                        $parentIdx = $eIdx;
                        break;
                    }
                }
            }

            // D. By Child Name/Patronymic Match (Matches displaced husbands like Mohamad Azhari with Niska's child Nabila Zahra Azhari)
            if ($parentIdx === null && $isHusband) {
                $hTokens = $getDistinctiveTokens(trim($row['nama_lengkap'] ?? $row['nama'] ?? ''));
                if (!empty($hTokens)) {
                    foreach ($employeeRows as $eIdx => $eInfo) {
                        $isFemale = str_contains($eInfo['gender'], 'PEREMPUAN') || str_contains($eInfo['gender'], 'WANITA') || $eInfo['gender'] === '2' || $eInfo['gender'] === 'P';
                        if ($isFemale && !empty($employeeChildren[$eIdx])) {
                            foreach ($employeeChildren[$eIdx] as $childName) {
                                $cTokens = $getDistinctiveTokens($childName);
                                if (!empty(array_intersect($hTokens, $cTokens))) {
                                    $parentIdx = $eIdx;
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }

            // E. SMART LOOKAHEAD: Suami / Kepala Keluarga positioned ABOVE female employee
            if ($parentIdx === null && $isHusband) {
                foreach ($employeeRows as $eIdx => $eInfo) {
                    if ($eIdx > $index) {
                        $isFemale = str_contains($eInfo['gender'], 'PEREMPUAN') || str_contains($eInfo['gender'], 'WANITA') || $eInfo['gender'] === '2' || $eInfo['gender'] === 'P';
                        $statusKawin = strtoupper(trim($eInfo['status_kawin'] ?? ''));
                        $isSingle = str_contains($statusKawin, 'BELUM') || str_contains($statusKawin, 'CERAI');
                        // Only link if female is married/not single, and either immediate next row or same address
                        if ($isFemale && !$isSingle) {
                            if ($eIdx === $index + 1 || (!empty($row['alamat']) && !empty($eInfo['alamat']) && (str_contains($eInfo['alamat'], $row['alamat']) || str_contains($row['alamat'], $eInfo['alamat'])))) {
                                $parentIdx = $eIdx;
                                break;
                            }
                        }
                        break;
                    }
                }
            }

            // F. By Address: married female employee without husband
            if ($parentIdx === null && $isHusband && !empty($row['alamat'])) {
                foreach ($employeeRows as $eIdx => $eInfo) {
                    $isFemale = str_contains($eInfo['gender'], 'PEREMPUAN') || str_contains($eInfo['gender'], 'WANITA') || $eInfo['gender'] === '2' || $eInfo['gender'] === 'P';
                    $statusKawin = strtoupper(trim($eInfo['status_kawin'] ?? ''));
                    $isMarried = !str_contains($statusKawin, 'BELUM') && !str_contains($statusKawin, 'CERAI');
                    if ($isFemale && $isMarried && !empty($eInfo['alamat']) && (str_contains($eInfo['alamat'], $row['alamat']) || str_contains($row['alamat'], $eInfo['alamat']))) {
                        $parentIdx = $eIdx;
                        break;
                    }
                }
            }

            // G. Fallback to nearest preceding employee
            if ($parentIdx === null) {
                $prev = array_filter(array_keys($employeeRows), fn($k) => $k < $index);
                if (!empty($prev)) {
                    $parentIdx = max($prev);
                } else {
                    // If no preceding employee, lookahead to first upcoming employee
                    $next = array_filter(array_keys($employeeRows), fn($k) => $k > $index);
                    if (!empty($next)) {
                        $parentIdx = min($next);
                    }
                }
            }

            $familyParentIndexMap[$index] = $parentIdx;
        }

        // -------------------------------------------------------------
        // STEP 3: EXECUTE - PASS 1: UPSERT PRINCIPAL EMPLOYEES
        // -------------------------------------------------------------
        $savedEmployees = []; // row_index => Employee model
        $lastProcessedEmployee = null;

        foreach ($items as $index => $row) {
            if (!isset($employeeRows[$index])) continue;
            $lineNum = $index + 1;

            $nama = trim($row['nama_lengkap'] ?? $row['nama'] ?? '');
            $nip = trim($row['nip'] ?? '');
            $nik = trim($row['nik'] ?? '');
            $noKk = trim($row['nomor_kartu_keluarga'] ?? '');

            if (empty($nama)) {
                $skipped++;
                $errorDetails[] = "Baris {$lineNum}: Nama karyawan kosong";
                continue;
            }

            // Find existing employee
            $existingEmployee = null;
            if (!empty($nip)) {
                $existingEmployee = Employee::where('nip', $nip)->first();
            }
            if (!$existingEmployee && !empty($nik)) {
                $existingEmployee = Employee::where('nik', $nik)->first();
            }

            $dept = !empty($row['departemen']) ? trim($row['departemen']) : null;
            if ($dept) {
                Department::firstOrCreate(['name' => $dept]);
            }

            $outhalVal = !empty($row['outhal']) ? trim($row['outhal']) : null;
            $statusKarRaw = strtoupper(trim($row['status_karyawan'] ?? ''));
            $statusHubRaw = strtoupper(trim($row['status_hubungan_kerja'] ?? ''));

            if ($statusKarRaw === 'ACTIVE' || (!empty($statusKarRaw) && !str_contains($statusKarRaw, 'NON') && str_contains($statusKarRaw, 'ACTI'))) {
                $statusKar = 'ACTIVE';
            } else {
                $isNonActive = (!empty($outhalVal) && $outhalVal !== '-')
                    || str_contains($statusKarRaw, 'NON')
                    || str_contains($statusKarRaw, 'TIDAK')
                    || str_contains($statusKarRaw, 'KELUAR')
                    || str_contains($statusKarRaw, 'PHK')
                    || str_contains($statusKarRaw, 'RESIGN')
                    || str_contains($statusKarRaw, 'OFF');

                $statusKar = $isNonActive ? 'NON ACTIVE' : 'ACTIVE';
            }
            $statusHub = str_contains($statusHubRaw, 'SKPKT') ? 'SKPKT' : (str_contains($statusHubRaw, 'PKWTT') || str_contains($statusHubRaw, 'TETAP') ? 'PKWTT' : 'PKWT');

            $inParsed = $parseDate($row['in'] ?? null);
            $outtodayParsed = $parseDate($row['outtoday'] ?? null);
            if ($statusHub === 'PKWT' && !empty($inParsed) && empty($outtodayParsed)) {
                try {
                    $outtodayParsed = Carbon::parse($inParsed)->addMonths(6)->subDay()->format('Y-m-d');
                } catch (\Exception $e) {}
            }

            $empData = [
                'bendera'                       => !empty($row['bendera']) ? trim($row['bendera']) : null,
                'kode'                          => !empty($row['kode']) ? trim($row['kode']) : null,
                'pisat'                         => !empty($row['pisat']) ? trim($row['pisat']) : null,
                'peserta'                       => !empty($row['peserta']) ? trim($row['peserta']) : null,
                'nip'                           => $nip ?: null,
                'jabatan'                       => !empty($row['jabatan']) ? trim($row['jabatan']) : null,
                'departemen'                    => $dept,
                'in'                            => $inParsed,
                'outtoday'                      => $outtodayParsed,
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
                'kewarganegaraan_bpjs'          => !empty($row['kewarganegaraan_bpjs']) ? trim($row['kewarganegaraan_bpjs']) : '1 = WNI',
                'sub_cabang'                    => !empty($row['sub_cabang']) ? trim($row['sub_cabang']) : null,
                'catatan'                       => !empty($row['catatan']) ? trim($row['catatan']) : null,
            ];

            try {
                if ($existingEmployee) {
                    $updateData = array_filter($empData, fn($v) => $v !== null && $v !== '');
                    $updateData['nama_lengkap'] = $nama;
                    $updateData['status_karyawan'] = $statusKar;
                    $updateData['status_hubungan_kerja'] = $statusHub;
                    $existingEmployee->update($updateData);
                    $targetEmpModel = $existingEmployee;
                    $updatedEmployees++;
                } else {
                    $targetEmpModel = Employee::create($empData);
                    $importedEmployees++;
                }

                // Sync Contracts 1-10
                $hasImportedContracts = false;
                if (!empty($row['contracts']) && is_array($row['contracts'])) {
                    foreach ($row['contracts'] as $c) {
                        if (!empty($c['tanggal_mulai']) && !empty($c['tanggal_selesai'])) {
                            $cStart = $parseDate($c['tanggal_mulai']);
                            $cEnd = $parseDate($c['tanggal_selesai']);
                            if ($cStart && $cEnd) {
                                $diffM = (int) max(1, ceil(abs(strtotime($cEnd) - strtotime($cStart)) / (30 * 86400)));
                                ContractHistory::updateOrCreate(
                                    [
                                        'employee_id' => $targetEmpModel->id,
                                        'kontrak_ke'  => (int) ($c['kontrak_ke'] ?? 1),
                                    ],
                                    [
                                        'tanggal_mulai'      => $cStart,
                                        'tanggal_selesai'    => $cEnd,
                                        'masa_kontrak_bulan' => !empty($c['masa_kontrak_bulan']) ? (int) $c['masa_kontrak_bulan'] : $diffM,
                                        'diserahkan'         => !empty($c['diserahkan']) ? trim($c['diserahkan']) : null,
                                        'catatan'            => !empty($c['catatan']) ? trim($c['catatan']) : null,
                                    ]
                                );
                                $hasImportedContracts = true;
                            }
                        }
                    }
                }

                if (!$hasImportedContracts && $statusHub === 'PKWT' && !empty($inParsed)) {
                    try {
                        $effEnd = $outtodayParsed ?: Carbon::parse($inParsed)->addMonths(6)->subDay()->format('Y-m-d');
                        ContractHistory::firstOrCreate(
                            ['employee_id' => $targetEmpModel->id, 'kontrak_ke' => 1],
                            [
                                'tanggal_mulai'      => $inParsed,
                                'tanggal_selesai'    => $effEnd,
                                'masa_kontrak_bulan' => 6,
                                'diserahkan'         => 'Sudah',
                                'catatan'            => 'Kontrak Pertama (Minimal 6 Bulan)',
                            ]
                        );
                    } catch (\Exception $e) {}
                }

                $savedEmployees[$index] = $targetEmpModel;
                $lastProcessedEmployee = $targetEmpModel;
            } catch (\Exception $e) {
                $skipped++;
                $errorDetails[] = "Baris {$lineNum} (Karyawan {$nama}): " . $e->getMessage();
            }
        }

        // -------------------------------------------------------------
        // STEP 4: EXECUTE - PASS 2: UPSERT FAMILY MEMBERS
        // -------------------------------------------------------------
        foreach ($items as $index => $row) {
            if (isset($employeeRows[$index])) continue;
            $lineNum = $index + 1;

            $nama = trim($row['nama_lengkap'] ?? $row['nama'] ?? '');
            if (empty($nama)) {
                $skipped++;
                $errorDetails[] = "Baris {$lineNum}: Nama keluarga kosong";
                continue;
            }

            $parentIdx = $familyParentIndexMap[$index] ?? null;
            $parentEmp = ($parentIdx !== null && isset($savedEmployees[$parentIdx])) 
                ? $savedEmployees[$parentIdx] 
                : $lastProcessedEmployee;

            if (!$parentEmp) {
                $skipped++;
                $errorDetails[] = "Baris {$lineNum} (Keluarga {$nama}): Karyawan induk tidak ditemukan";
                continue;
            }

            try {
                $pisat = trim($row['pisat'] ?? $row['pisat_bpjs'] ?? '');
                $nik = trim($row['nik'] ?? '');

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

                // VALIDASI KEPALA KELUARGA / ORANG TUA:
                // Jika karyawan induk BELUM MENIKAH atau CERAI, tidak boleh memiliki SUAMI atau ISTRI!
                // Kepala keluarga laki-laki di KK karyawan perempuan lajang adalah AYAH / ORANG TUA.
                $parentStatus = strtoupper(trim($parentEmp->status_kawin ?? ''));
                $parentIsSingle = str_contains($parentStatus, 'BELUM') || str_contains($parentStatus, 'CERAI');
                $parentNamaAyah = strtoupper(trim($parentEmp->nama_ayah ?? ''));
                $parentNamaIbu = strtoupper(trim($parentEmp->nama_ibu ?? ''));
                $famNama = strtoupper($nama);

                // REGULASI BPJS KESEHATAN PERUSAHAAN (PPU):
                // Orang Tua (Ayah/Ibu) dan Saudara (Kakak/Adik) BUKAN tanggungan pokok BPJS perusahaan.
                // Jika karyawan induk BELUM MENIKAH atau CERAI, baris non-anak tidak dimasukkan ke daftar tanggungan BPJS!
                if ($parentIsSingle && $hubungan !== 'ANAK') {
                    $skipped++;
                    continue;
                }
                if (in_array($hubungan, ['AYAH', 'IBU', 'ORANG TUA', 'ANGGOTA KELUARGA', 'LAINNYA'])) {
                    $skipped++;
                    continue;
                }

                $familyData = [
                    'employee_id'             => $parentEmp->id,
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
                    'alamat'                  => !empty($row['alamat']) ? trim($row['alamat']) : $parentEmp->alamat,
                ];

                $existingFamily = EmployeeFamily::where('employee_id', $parentEmp->id)
                    ->where('nama_lengkap', $nama)
                    ->first();

                if ($existingFamily) {
                    $updateFamilyData = array_filter($familyData, fn($v) => $v !== null && $v !== '');
                    unset($updateFamilyData['employee_id']);
                    $existingFamily->update($updateFamilyData);
                    $updatedFamilies++;
                } else {
                    EmployeeFamily::create($familyData);
                    $importedFamilies++;
                }
            } catch (\Exception $e) {
                $skipped++;
                $errorDetails[] = "Baris {$lineNum} (Keluarga {$nama}): " . $e->getMessage();
            }
        }

        $msgParts = [];
        if ($importedEmployees > 0) $msgParts[] = "{$importedEmployees} karyawan baru";
        if ($updatedEmployees > 0) $msgParts[] = "{$updatedEmployees} karyawan diperbarui";
        if ($importedFamilies > 0) $msgParts[] = "{$importedFamilies} keluarga baru";
        if ($updatedFamilies > 0) $msgParts[] = "{$updatedFamilies} keluarga diperbarui";
        $msg = !empty($msgParts) ? implode(', ', $msgParts) . ' berhasil diproses!' : 'Tidak ada data yang diproses.';
        if ($skipped > 0) $msg .= " ({$skipped} dilewati)";

        self::flushManpowerCache();

        return response()->json([
            'message'             => $msg,
            'imported'            => $importedEmployees,
            'imported_employees'  => $importedEmployees,
            'updated_employees'   => $updatedEmployees,
            'imported_families'   => $importedFamilies,
            'updated_families'    => $updatedFamilies,
            'skipped'             => $skipped,
            'error_details'       => $errorDetails,
        ]);
    }

    /**
     * REPAIR & RECONCILE FAMILY RELATIONS
     * Fixes husbands/heads of household misplaced above or under the wrong employee.
     * Can be run anytime without re-uploading Excel.
     */
    public function repairFamilies(): JsonResponse
    {
        $fixedCount = 0;
        $details = [];

        try {
            DB::beginTransaction();

            // 1. Check all husbands in employee_families
            $suamiFamilies = EmployeeFamily::where(function ($q) {
                $q->where('hubungan', 'like', '%SUAMI%')
                  ->orWhere('pisat', 'like', '%2%')
                  ->orWhere('pisat', 'like', '%SUAMI%');
            })->get();

            // Distinctive tokens helper
            $getDistinctiveTokens = function ($name) {
                $generic = [
                    'MOHAMAD', 'MOHAMMAD', 'MUHAMMAD', 'MUHAMAD', 'ACHMAD', 'AHMAD',
                    'ABDUL', 'SITI', 'NUR', 'BIN', 'BINTI', 'PUTRA', 'PUTRI', 'DEWI'
                ];
                $words = preg_split('/[^A-Z0-9]+/', strtoupper($name ?? ''));
                return array_values(array_filter($words, function ($w) use ($generic) {
                    return strlen($w) >= 4 && !in_array($w, $generic);
                }));
            };

            foreach ($suamiFamilies as $fam) {
                $parent = Employee::find($fam->employee_id);
                if (!$parent) continue;

                $parentGender = strtoupper(trim($parent->jenis_kelamin ?? ''));
                $parentIsMale = str_contains($parentGender, 'LAKI') || $parentGender === '1' || $parentGender === 'L';
                $parentStatusKawin = strtoupper(trim($parent->status_kawin ?? ''));
                $parentIsSingle = str_contains($parentStatusKawin, 'BELUM') || str_contains($parentStatusKawin, 'CERAI');

                // JIKA KARYAWAN BELUM MENIKAH / CERAI:
                // Kepala keluarga laki-laki di KK-nya adalah Ayah/Saudara, BUKAN tanggungan BPJS perusahaan!
                if ($parentIsSingle) {
                    $fam->delete();
                    $fixedCount++;
                    $details[] = "Data '{$fam->nama_lengkap}' pada karyawan belum menikah '{$parent->nama_lengkap}' ({$parentStatusKawin}) dihapus dari tanggungan BPJS (karena orang tua/saudara bukan tanggungan BPJS perusahaan).";
                    continue;
                }

                // Check if parent has duplicate husbands
                $hasMultipleSuamis = EmployeeFamily::where('employee_id', $parent->id)
                    ->where('id', '!=', $fam->id)
                    ->where(function ($q) {
                        $q->where('hubungan', 'like', '%SUAMI%')
                          ->orWhere('pisat', 'like', '%2%')
                          ->orWhere('pisat', 'like', '%SUAMI%');
                    })->exists();

                // Check if any married female employee has a registered child matching this husband's name token (e.g. AZHARI -> Nabila Zahra Azhari)
                $betterFemaleByChild = null;
                $hTokens = $getDistinctiveTokens($fam->nama_lengkap);
                if (!empty($hTokens)) {
                    $potentialMothers = Employee::where('id', '!=', $parent->id)
                        ->where(function ($q) {
                            $q->where('jenis_kelamin', 'like', '%PEREMPUAN%')
                              ->orWhere('jenis_kelamin', 'like', '%WANITA%')
                              ->orWhere('jenis_kelamin', '2')
                              ->orWhere('jenis_kelamin', 'P');
                        })
                        ->whereDoesntHave('families', function ($q) {
                            $q->where('hubungan', 'like', '%SUAMI%')
                              ->orWhere('pisat', 'like', '%2%')
                              ->orWhere('pisat', 'like', '%SUAMI%');
                        })
                        ->with('families')
                        ->get();

                    foreach ($potentialMothers as $potMother) {
                        foreach ($potMother->families as $childFam) {
                            $cTokens = $getDistinctiveTokens($childFam->nama_lengkap);
                            if (!empty(array_intersect($hTokens, $cTokens))) {
                                $betterFemaleByChild = $potMother;
                                break 2;
                            }
                        }
                    }
                }

                // Anomaly: Parent is male, OR has multiple husbands, OR parent is single/unmarried, OR husband's child belongs to another mother
                if ($parentIsMale || $hasMultipleSuamis || $parentIsSingle || ($betterFemaleByChild !== null)) {
                    $targetFemale = $betterFemaleByChild;

                    // A. By KK match if not matched by child
                    if (!$targetFemale && !empty($parent->nomor_kartu_keluarga)) {
                        $targetFemale = Employee::where('id', '!=', $parent->id)
                            ->where('nomor_kartu_keluarga', $parent->nomor_kartu_keluarga)
                            ->where(function ($q) {
                                $q->where('jenis_kelamin', 'like', '%PEREMPUAN%')
                                  ->orWhere('jenis_kelamin', 'like', '%WANITA%')
                                  ->orWhere('jenis_kelamin', '2')
                                  ->orWhere('jenis_kelamin', 'P');
                            })
                            ->whereDoesntHave('families', function ($q) {
                                $q->where('hubungan', 'like', '%SUAMI%')
                                  ->orWhere('pisat', 'like', '%2%')
                                  ->orWhere('pisat', 'like', '%SUAMI%');
                            })
                            ->first();
                    }

                    // B. By same address (only match married female without husband)
                    if (!$targetFemale && !empty($fam->alamat)) {
                        $targetFemale = Employee::where(function ($q) {
                                $q->where('jenis_kelamin', 'like', '%PEREMPUAN%')
                                  ->orWhere('jenis_kelamin', 'like', '%WANITA%')
                                  ->orWhere('jenis_kelamin', '2')
                                  ->orWhere('jenis_kelamin', 'P');
                            })
                            ->where('id', '!=', $parent->id)
                            ->where('alamat', 'like', '%' . trim($fam->alamat) . '%')
                            ->where(function ($q) {
                                $q->where('status_kawin', 'like', '%MENIKAH%')
                                  ->orWhere('status_kawin', 'like', '%KAWIN%');
                            })
                            ->whereDoesntHave('families', function ($q) {
                                $q->where('hubungan', 'like', '%SUAMI%')
                                  ->orWhere('pisat', 'like', '%2%')
                                  ->orWhere('pisat', 'like', '%SUAMI%');
                            })
                            ->first();
                    }

                    // C. Fallback: nearest married female employee registered after parent without husband
                    if (!$targetFemale) {
                        $targetFemale = Employee::where('id', '>', $parent->id)
                            ->where(function ($q) {
                                $q->where('jenis_kelamin', 'like', '%PEREMPUAN%')
                                  ->orWhere('jenis_kelamin', 'like', '%WANITA%')
                                  ->orWhere('jenis_kelamin', '2')
                                  ->orWhere('jenis_kelamin', 'P');
                            })
                            ->where(function ($q) {
                                $q->where('status_kawin', 'like', '%MENIKAH%')
                                  ->orWhere('status_kawin', 'like', '%KAWIN%');
                            })
                            ->whereDoesntHave('families', function ($q) {
                                $q->where('hubungan', 'like', '%SUAMI%')
                                  ->orWhere('pisat', 'like', '%2%')
                                  ->orWhere('pisat', 'like', '%SUAMI%');
                            })
                            ->orderBy('id', 'asc')
                            ->first();
                    }

                    // D. Fallback: closest female employee after parent without husband
                    if (!$targetFemale) {
                        $targetFemale = Employee::where('id', '>', $parent->id)
                            ->where(function ($q) {
                                $q->where('jenis_kelamin', 'like', '%PEREMPUAN%')
                                  ->orWhere('jenis_kelamin', 'like', '%WANITA%')
                                  ->orWhere('jenis_kelamin', '2')
                                  ->orWhere('jenis_kelamin', 'P');
                            })
                            ->whereDoesntHave('families', function ($q) {
                                $q->where('hubungan', 'like', '%SUAMI%')
                                  ->orWhere('pisat', 'like', '%2%')
                                  ->orWhere('pisat', 'like', '%SUAMI%');
                            })
                            ->orderBy('id', 'asc')
                            ->first();
                    }

                    if ($targetFemale && $targetFemale->id !== $parent->id) {
                        $oldParentName = $parent->nama_lengkap;
                        $fam->employee_id = $targetFemale->id;
                        $fam->save();

                        $fixedCount++;
                        $details[] = "Kepala Keluarga/Suami '{$fam->nama_lengkap}' dipindahkan dari {$oldParentName} ke '{$targetFemale->nama_lengkap}' (NIP: " . ($targetFemale->nip ?: '-') . ")";
                    }
                }
            }

            // 2. Check and clean any non-BPJS relations (AYAH, IBU, ORANG TUA, ANGGOTA KELUARGA, LAINNYA)
            $nonBpjsFamilies = EmployeeFamily::whereIn('hubungan', ['AYAH', 'IBU', 'ORANG TUA', 'ANGGOTA KELUARGA', 'LAINNYA'])->get();
            foreach ($nonBpjsFamilies as $nonBpjs) {
                $parentName = $nonBpjs->employee ? $nonBpjs->employee->nama_lengkap : 'ID ' . $nonBpjs->employee_id;
                $details[] = "Data '{$nonBpjs->nama_lengkap}' ({$nonBpjs->hubungan}) pada karyawan '{$parentName}' dihapus dari tanggungan BPJS.";
                $nonBpjs->delete();
                $fixedCount++;
            }

            // 3. Check orphan employees (heads of household accidentally created as Employee without NIP and Jabatan)
            $orphanEmployees = Employee::where(function ($q) {
                $q->whereNull('nip')->orWhere('nip', '');
            })->where(function ($q) {
                $q->whereNull('jabatan')->orWhere('jabatan', '');
            })->get();

            foreach ($orphanEmployees as $orphan) {
                $orphanGender = strtoupper(trim($orphan->jenis_kelamin ?? ''));
                $orphanIsMale = str_contains($orphanGender, 'LAKI') || $orphanGender === '1' || $orphanGender === 'L';

                if ($orphanIsMale) {
                    // Find female employee right after this orphan
                    $targetFemale = Employee::where('id', '>', $orphan->id)
                        ->where(function ($q) {
                            $q->where('jenis_kelamin', 'like', '%PEREMPUAN%')
                              ->orWhere('jenis_kelamin', 'like', '%WANITA%')
                              ->orWhere('jenis_kelamin', '2')
                              ->orWhere('jenis_kelamin', 'P');
                        })
                        ->where(function ($q) {
                            $q->whereNotNull('nip')->where('nip', '!=', '');
                        })
                        ->orderBy('id', 'asc')
                        ->first();

                    if ($targetFemale) {
                        EmployeeFamily::create([
                            'employee_id'             => $targetFemale->id,
                            'nama_lengkap'            => $orphan->nama_lengkap,
                            'hubungan'                => 'SUAMI',
                            'pisat'                   => '2',
                            'nik'                     => $orphan->nik,
                            'tempat_lahir'            => $orphan->tempat_lahir,
                            'tanggal_lahir'           => $orphan->tanggal_lahir,
                            'usia'                    => $orphan->usia,
                            'jenis_kelamin'           => $orphan->jenis_kelamin ?: 'LAKI-LAKI',
                            'status_kawin'            => $orphan->status_kawin ?: 'KAWIN',
                            'nomor_bpjs_kis'          => $orphan->nomor_bpjs_kis_anggota_keluarga ?: $orphan->nomor_bpjs_kis_peserta,
                            'kode_faskes_tk_1'        => $orphan->kode_faskes_tk_1,
                            'nama_faskes_tk_1'        => $orphan->nama_faskes_tk_1,
                            'kode_faskes_dokter_gigi' => $orphan->kode_faskes_dokter_gigi,
                            'nama_faskes_dokter_gigi' => $orphan->nama_faskes_dokter_gigi,
                            'alamat'                  => $orphan->alamat ?: $targetFemale->alamat,
                        ]);

                        $fixedCount++;
                        $details[] = "Data '{$orphan->nama_lengkap}' (tercatat sebagai karyawan tanpa NIP) dikonversi menjadi Suami dari '{$targetFemale->nama_lengkap}'";
                        $orphan->delete();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'success'     => true,
                'message'     => $fixedCount > 0
                    ? "Berhasil memperbaiki {$fixedCount} relasi kepala keluarga / suami!"
                    : "Semua relasi kepala keluarga dan anggota keluarga sudah sesuai.",
                'fixed_count' => $fixedCount,
                'details'     => $details,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbaiki relasi keluarga: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================
    // FAMILY CRUD
    // ============================

    public function storeFamily(Request $request, $employeeId): JsonResponse
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

    public function updateFamily(Request $request, $id): JsonResponse
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

    public function destroyFamily($id): JsonResponse
    {
        $family = EmployeeFamily::findOrFail($id);
        $family->delete();

        return response()->json(['message' => 'Data anggota keluarga berhasil dihapus']);
    }

    // ============================
    // CONTRACT HISTORY
    // ============================

    public function addContract(Request $request, $id): JsonResponse
    {
        $employee = Employee::with('contractHistories')->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tanggal_mulai'      => 'required|date',
            'tanggal_selesai'    => 'required|date|after:tanggal_mulai',
            'masa_kontrak_bulan' => 'required|integer|min:1',
            'kontrak_ke'         => 'nullable|integer|min:1',
            'catatan'            => 'nullable|string',
            'diserahkan'         => 'nullable|string|max:50',
            'sk_file'            => ['nullable', 'file', new SecureFile(['pdf', 'jpg', 'jpeg', 'png'], 10240)],
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        // 1. Determine highest existing contract from history
        $historyMax = $employee->contractHistories()->max('kontrak_ke') ?? 0;

        // 2. Determine current contract number from $employee->kontrak
        $currentEmpKontrakNum = 0;
        if (!empty($employee->kontrak)) {
            $rawK = strtoupper(trim($employee->kontrak));
            if (preg_match('/\d+/', $rawK, $m)) {
                $currentEmpKontrakNum = (int) $m[0];
            } else {
                $romans = ['X' => 10, 'IX' => 9, 'VIII' => 8, 'VII' => 7, 'VI' => 6, 'V' => 5, 'IV' => 4, 'III' => 3, 'II' => 2, 'I' => 1];
                foreach ($romans as $r => $val) {
                    if (preg_match('/(^|\s|_|-|PKWT|KONTRAK)' . $r . '($|\s|_|-|$)/i', $rawK)) {
                        $currentEmpKontrakNum = $val;
                        break;
                    }
                }
            }
        }
        if ($currentEmpKontrakNum === 0 && !empty($employee->outtoday)) {
            $currentEmpKontrakNum = 1;
        }

        $highestExisting = max($historyMax, $currentEmpKontrakNum);

        // Target contract number: use passed kontrak_ke if provided, otherwise advance to next
        if (!empty($data['kontrak_ke']) && intval($data['kontrak_ke']) > 0) {
            $targetKontrakKe = intval($data['kontrak_ke']);
        } else {
            $targetKontrakKe = max(1, $highestExisting + 1);
        }

        // If target is >= 2 but contract 1 history is missing, backfill contract 1 history
        if ($targetKontrakKe > 1 && $historyMax === 0 && $employee->in) {
            ContractHistory::firstOrCreate(
                ['employee_id' => $employee->id, 'kontrak_ke' => 1],
                [
                    'tanggal_mulai'      => $employee->in,
                    'tanggal_selesai'    => $employee->outtoday ?? $data['tanggal_mulai'],
                    'masa_kontrak_bulan' => 12,
                    'diserahkan'         => 'Sudah',
                    'catatan'            => 'Kontrak Pertama',
                ]
            );
        }

        $data['kontrak_ke'] = $targetKontrakKe;
        $data['employee_id'] = $employee->id;

        if ($request->hasFile('sk_file')) {
            $data['sk_path'] = $request->file('sk_file')->store('contract-sk', 'public');
        }
        unset($data['sk_file']);

        $history = ContractHistory::updateOrCreate(
            ['employee_id' => $employee->id, 'kontrak_ke' => $targetKontrakKe],
            $data
        );

        $employee->update([
            'kontrak'         => 'Kontrak ' . $targetKontrakKe,
            'outtoday'        => $data['tanggal_selesai'],
            'status_karyawan' => 'ACTIVE',
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
    // SK FILE DOWNLOAD & PREVIEW (CPanel & Vercel Friendly)
    // ============================

    public function downloadEmployeeSk(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);
        if (!$employee->sk_path) {
            abort(404, 'Karyawan ini belum memiliki lampiran SK.');
        }
        return $this->serveSkFile($request, $employee->sk_path, basename($employee->sk_path));
    }

    public function downloadContractSk(Request $request, $id)
    {
        $contract = ContractHistory::findOrFail($id);
        if (!$contract->sk_path) {
            abort(404, 'Kontrak ini belum memiliki lampiran SK.');
        }
        return $this->serveSkFile($request, $contract->sk_path, basename($contract->sk_path));
    }

    public function downloadSk(Request $request, string $filename)
    {
        $rawFilename = basename($filename);
        $decoded = urldecode($rawFilename);

        $candidates = array_unique([$rawFilename, $decoded]);
        foreach ($candidates as $cand) {
            foreach (['employee-sk', 'contract-sk', 'sk', 'uploads/sk'] as $dir) {
                $relPath = "{$dir}/{$cand}";
                if (Storage::disk('public')->exists($relPath)) {
                    return $this->serveSkFile($request, $relPath, $cand);
                }
            }
        }

        // Search in possible cPanel paths
        $searchDirs = [
            storage_path('app/public/employee-sk'),
            storage_path('app/public/contract-sk'),
            public_path('storage/employee-sk'),
            public_path('storage/contract-sk'),
            base_path('../public_html/storage/employee-sk'),
            base_path('../public_html/storage/contract-sk'),
        ];

        foreach ($searchDirs as $sDir) {
            if (!file_exists($sDir)) continue;
            foreach ($candidates as $cand) {
                $full = rtrim($sDir, '/\\') . DIRECTORY_SEPARATOR . $cand;
                if (file_exists($full) && is_file($full)) {
                    return $this->streamLocalFile($request, $full, $cand);
                }
            }
        }

        abort(404, 'File SK tidak ditemukan di server.');
    }

    private function serveSkFile(Request $request, string $storagePath, string $filename)
    {
        if (Storage::disk('public')->exists($storagePath)) {
            $fullPath = Storage::disk('public')->path($storagePath);
            if (file_exists($fullPath)) {
                return $this->streamLocalFile($request, $fullPath, $filename);
            }
            return Storage::disk('public')->response($storagePath, $filename);
        }

        abort(404, 'Berkas fisik SK tidak ditemukan.');
    }

    private function streamLocalFile(Request $request, string $filePath, string $filename)
    {
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        $disposition = $request->query('download') === '1' ? 'attachment' : 'inline';

        return response()->file($filePath, [
            'Content-Type'        => $mime,
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'       => 'public, max-age=86400',
        ]);
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
        self::flushManpowerCache();
        return response()->json($dept, 201);
    }

    public function updateDepartment(Request $request, int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);
        $request->validate([
            'name' => "required|string|max:255|unique:departments,name,{$id}",
        ]);

        $dept->update(['name' => $request->name]);
        self::flushManpowerCache();
        return response()->json($dept);
    }

    public function destroyDepartment(int $id): JsonResponse
    {
        $dept = Department::findOrFail($id);
        $dept->delete();
        self::flushManpowerCache();
        return response()->json(['message' => 'Departemen berhasil dihapus']);
    }
}
