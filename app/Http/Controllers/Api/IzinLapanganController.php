<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\IzinRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Izin Lapangan Mandiri: pengajuan sakit/cuti/izin karyawan lapangan,
 * disetujui pengawas satu departemen. Login nama + NIP (tanpa akun user).
 */
class IzinLapanganController extends Controller
{
    private const JENIS = ['sakit', 'cuti', 'izin'];
    private const KUOTA_CUTI = 18;
    private const MAKS_CUTI_SEKALI = 6;
    private const TOKEN_HARI = 14;

    /**
     * Jenjang Level Jabatan Persetujuan:
     * Mengambil level dinamis dari tabel izin_approval_hierarchies yang diatur oleh Super Admin.
     * Level 0 = Crew/Bawahan (Driver, Mekanik, Operator, Helper, dll.)
     * Level 1 = Foreman, Leader, Kordinator, Supervisor
     * Level 2 = Superintendent, Penanggung Jawab, Kabag
     * Level 3 = Deputy
     * Level 4 = PJO
     */
    public static function getJobLevel(?string $jabatan): int
    {
        if (empty($jabatan)) {
            return 0;
        }

        $j = Str::upper(trim((string) $jabatan));

        // 1. Cek dari konfigurasi dinamis database (diatur oleh Super Admin)
        try {
            $rules = \Illuminate\Support\Facades\Cache::remember('izin_hierarchy_rules', 300, function () {
                return \App\Models\IzinApprovalHierarchy::where('is_active', true)
                    ->orderByDesc('level')
                    ->get(['keyword', 'level']);
            });

            foreach ($rules as $r) {
                $kw = Str::upper(trim($r->keyword));
                if (!empty($kw) && str_contains($j, $kw)) {
                    return (int) $r->level;
                }
            }
        } catch (\Throwable $e) {
            // fallback jika table belum siap
        }

        // 2. Fallback pola dasar
        if (preg_match('/\bPJO\b/i', $j)) {
            return 4;
        }
        if (preg_match('/\bDEPUTY\b/i', $j)) {
            return 3;
        }
        if (preg_match('/SUPERINTENDENT|PENANGGUNG\s*JAWAB|KABAG|\bKAPRO\b|\bMANAGER\b/i', $j)) {
            return 2;
        }
        if (preg_match('/FOREMAN|SUPERVISOR|\bLEADER\b|KORDINATOR|KOORDINATOR/i', $j)) {
            return 1;
        }
        return 0; // Crew / Staf biasa
    }

    /**
     * Cek apakah user berhak meng-ACC (Level 1 ke atas)
     */
    private function isPengawas(Employee $e): bool
    {
        return self::getJobLevel($e->jabatan) >= 1;
    }

    /**
     * Ekstrak kode area/site dari nama jabatan (misal "- SMC", "- OB", "- CH", "- SMD")
     */
    public static function extractAreaTag(?string $jabatan): ?string
    {
        if (preg_match('/-\s*([A-Z0-9\/]+)$/i', trim((string) $jabatan), $m)) {
            return Str::upper(trim($m[1]));
        }
        return null;
    }

    /**
     * Cek apakah seorang pengawas cocok dengan pemohon berdasarkan Departemen dan Area
     */
    public static function isMatchDeptAndArea(Employee $supervisor, Employee $karyawan): bool
    {
        // 1. Departemen wajib sama (kecuali Deputy / PJO yang mencakup seluruh departemen site)
        $supLevel = self::getJobLevel($supervisor->jabatan);
        if ($supLevel < 3) {
            $supDept = Str::lower(trim((string) $supervisor->departemen));
            $empDept = Str::lower(trim((string) $karyawan->departemen));
            if ($supDept !== $empDept) {
                return false;
            }
        }

        // 2. Jika pengawas memiliki kode area spesifik di jabatannya, harus cocok dengan kode area karyawan
        $supArea = self::extractAreaTag($supervisor->jabatan);
        $empArea = self::extractAreaTag($karyawan->jabatan);

        if ($supArea && $empArea && $supArea !== $empArea) {
            return false;
        }

        return true;
    }

    /**
     * Normalisasi nama untuk pencocokan toleran (hapus tanda baca, multiple space, uppercase)
     */
    private function norm(?string $str): string
    {
        if (empty($str)) {
            return '';
        }
        $clean = preg_replace('/[^a-zA-Z0-9]/', '', (string) $str);
        return Str::upper(trim((string) $clean));
    }

    private function aktif()
    {
        return Employee::query()
            ->select(['id', 'nama_lengkap', 'nip', 'nik', 'jabatan', 'departemen', 'dilokasi', 'status_karyawan'])
            ->where('status_karyawan', 'ACTIVE');
    }

    /** Ambil karyawan dari header Authorization: Bearer <token>; null jika tidak sah. */
    private function me(Request $r): ?Employee
    {
        $t = $r->bearerToken() ?: $r->query('t'); // ?t= dipakai tautan lampiran yang dibuka di tab baru
        if (!$t) {
            return null;
        }
        try {
            $p = json_decode(Crypt::decryptString($t), true);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($p) || ($p['exp'] ?? 0) < time()) {
            return null;
        }
        return $this->aktif()->find($p['id'] ?? 0);
    }

    private function userArr(Employee $e): array
    {
        return [
            'id' => $e->id,
            'nama' => $e->nama_lengkap,
            'nip' => $e->nip,
            'jabatan' => $e->jabatan,
            'departemen' => $e->departemen,
            'pengawas' => $this->isPengawas($e),
            'job_level' => self::getJobLevel($e->jabatan),
            'area_tag' => self::extractAreaTag($e->jabatan),
            'dilokasi' => (bool) $e->dilokasi,
        ];
    }

    private function reqArr(IzinRequest $p): array
    {
        return [
            'id' => $p->id,
            'jenis' => $p->jenis,
            'mulai' => $p->mulai->toDateString(),
            'selesai' => $p->selesai->toDateString(),
            'durasi' => $p->durasi,
            'alasan' => $p->alasan,
            'status' => $p->status,
            'catatan' => $p->catatan,
            'current_step' => $p->current_step ?? 'tahap_1',
            'lampiran' => $p->lampiran_path ? url('api/public/izin/requests/' . $p->id . '/lampiran') : null,
            'diajukan' => $p->created_at?->toIso8601String(),
            'diputuskan' => $p->decided_at?->toIso8601String(),
            'karyawan' => $p->employee ? [
                'nama' => $p->employee->nama_lengkap,
                'nip' => $p->employee->nip,
                'jabatan' => $p->employee->jabatan,
                'departemen' => $p->employee->departemen,
                'job_level' => self::getJobLevel($p->employee->jabatan),
            ] : null,
            'pengawas' => $p->supervisor?->nama_lengkap,
            'pengawas_id' => $p->supervisor_id,
            'diputuskan_oleh' => $p->decider?->nama_lengkap,
            // Multi-tier tracking details
            'step_1' => [
                'by_nama' => $p->step1By?->nama_lengkap,
                'by_jabatan' => $p->step1By?->jabatan,
                'at' => $p->step_1_at?->toIso8601String(),
                'status' => $p->step_1_status,
                'catatan' => $p->step_1_catatan,
            ],
            'step_2' => [
                'by_nama' => $p->step2By?->nama_lengkap,
                'by_jabatan' => $p->step2By?->jabatan,
                'at' => $p->step_2_at?->toIso8601String(),
                'status' => $p->step_2_status,
                'catatan' => $p->step_2_catatan,
            ],
        ];
    }

    private function sisaCuti(Employee $e): array
    {
        $pakai = (int) IzinRequest::where('employee_id', $e->id)
            ->where('jenis', 'cuti')->where('status', 'disetujui')
            ->whereYear('mulai', now()->year)->sum('durasi');
        return ['kuota' => self::KUOTA_CUTI, 'pakai' => $pakai, 'sisa' => self::KUOTA_CUTI - $pakai];
    }

    public function login(Request $r)
    {
        $d = $r->validate(['nama' => 'required|string|max:150', 'nip' => 'required|string|max:50']);
        $no = trim($d['nip']);
        $cleanNip = preg_replace('/[^a-zA-Z0-9]/', '', $no);
        $normInputNama = $this->norm($d['nama']);

        // Batas percobaan per NIP + IP: 5 gagal per 10 menit
        $key = 'izin-login:' . Str::lower($no) . '|' . $r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $m = (int) ceil(RateLimiter::availableIn($key) / 60);
            return response()->json(['message' => "Terlalu banyak percobaan. Coba lagi dalam {$m} menit."], 429);
        }

        // Cari karyawan ACTIVE yang NIP atau NIK cocok
        $e = $this->aktif()
            ->get()
            ->first(function ($x) use ($no, $cleanNip, $normInputNama) {
                $dbNipClean = preg_replace('/[^a-zA-Z0-9]/', '', (string) $x->nip);
                $dbNikClean = preg_replace('/[^a-zA-Z0-9]/', '', (string) $x->nik);

                $nipMatch = (strcasecmp(trim((string)$x->nip), $no) === 0)
                    || (!empty($cleanNip) && ($dbNipClean === $cleanNip || $dbNikClean === $cleanNip));

                if (!$nipMatch) {
                    return false;
                }

                $dbNamaNorm = $this->norm($x->nama_lengkap);
                return $dbNamaNorm === $normInputNama
                    || str_contains($dbNamaNorm, $normInputNama)
                    || str_contains($normInputNama, $dbNamaNorm);
            });

        if (!$e) {
            RateLimiter::hit($key, 600);
            return response()->json(['message' => 'Nama atau NIP tidak cocok dengan data HR / status karyawan tidak aktif.'], 422);
        }
        RateLimiter::clear($key);
        $token = Crypt::encryptString(json_encode(['id' => $e->id, 'exp' => time() + self::TOKEN_HARI * 86400]));
        return response()->json(['token' => $token, 'user' => $this->userArr($e)]);
    }

    /** Semua data yang dibutuhkan beranda: profil, pengawas, riwayat, antrean. */
    public function bootstrap(Request $r)
    {
        $me = $this->me($r);
        if (!$me) {
            return response()->json(['message' => 'Sesi berakhir. Silakan masuk lagi.'], 401);
        }

        // Hitung fingerprint cepat untuk ETag (meminimalisir transfer data & parsing JSON)
        $isPengawas = $this->isPengawas($me);
        $lastReqUpdate = IzinRequest::where(function ($q) use ($me, $isPengawas) {
            $q->where('employee_id', $me->id);
            if ($isPengawas) {
                $q->orWhere('supervisor_id', $me->id);
            }
        })->max('updated_at');

        $etag = sha1($me->id . '|' . (int)$me->dilokasi . '|' . ($lastReqUpdate ?? 'none'));
        if ($r->header('If-None-Match') === '"' . $etag . '"' || $r->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', '"' . $etag . '"');
        }

        $myLevel = self::getJobLevel($me->jabatan);

        // Cari daftar pengawas yang levelnya di atas saya dan cocok Departemen & Area
        $pengawas = $this->aktif()->where('id', '!=', $me->id)->get()
            ->filter(function ($e) use ($me, $myLevel) {
                $eLevel = self::getJobLevel($e->jabatan);
                // Pengawas yang dipilih harus berlevel di atas pemohon
                if ($eLevel <= $myLevel) {
                    return false;
                }
                // Jika pemohon level 0 (crew), pengawas target utamanya adalah Level 1 (Foreman/Supervisor)
                if ($myLevel === 0 && $eLevel > 2) {
                    return false; // Jangan langsung loncat ke Deputy/PJO jika masih ada level 1 & 2
                }
                return self::isMatchDeptAndArea($e, $me);
            })
            ->sortByDesc(fn ($e) => (int) $e->dilokasi)->values()
            ->map(fn ($e) => $this->userArr($e));

        $own = IzinRequest::where('employee_id', $me->id)
            ->latest()->limit(50)->get()->map(fn ($p) => $this->reqArr($p));

        $out = [
            'user' => $this->userArr($me),
            'pengawas' => $pengawas,
            'riwayat' => $own,
            'sisa_cuti' => $this->sisaCuti($me),
        ];

        // Jika user adalah Pengawas/Pejabat Approval (Level 1 ke atas)
        if ($isPengawas) {
            // Antrean Menunggu:
            // 1. Pengawas Tingkat 1 (Foreman/Supervisor): Permintaan di tahap_1 yang ditujukan kepadanya
            // 2. Pejabat Tingkat 2 (Superintendent/Kabag/Deputy): Permintaan di tahap_2 (sudah lolos tahap_1) yang cocok departemen/areanya
            $qMenunggu = IzinRequest::where(function ($q) use ($me, $myLevel) {
                if ($myLevel === 1) {
                    // Level 1 meng-ACC tahap 1
                    $q->where('supervisor_id', $me->id)
                      ->where('current_step', 'tahap_1')
                      ->where('status', 'menunggu');
                } elseif ($myLevel >= 2) {
                    // Level 2+ meng-ACC tahap 2 setelah lolos tahap 1
                    $q->where('current_step', 'tahap_2')
                      ->where('status', 'menunggu')
                      ->whereHas('employee', function ($eq) use ($me) {
                          if (self::getJobLevel($me->jabatan) < 3) {
                              $eq->where('departemen', $me->departemen);
                          }
                      });
                    
                    // Atau jika dia ditunjuk langsung sebagai supervisor_id di tahap 1 (bila pemohon adalah Foreman)
                    $q->orWhere(function ($sub) use ($me) {
                        $sub->where('supervisor_id', $me->id)
                            ->where('current_step', 'tahap_1')
                            ->where('status', 'menunggu');
                    });
                }
            });

            $out['menunggu'] = $qMenunggu->oldest()->get()->filter(function ($p) use ($me) {
                return self::isMatchDeptAndArea($me, $p->employee);
            })->values()->map(fn ($p) => $this->reqArr($p));

            // Riwayat keputusan yang pernah dia putuskan
            $out['keputusan'] = IzinRequest::where(function ($q) use ($me) {
                $q->where('step_1_by', $me->id)->orWhere('step_2_by', $me->id)->orWhere('decided_by', $me->id);
            })->latest('updated_at')->limit(50)->get()->map(fn ($p) => $this->reqArr($p));
        }
        return response()->json($out)->header('ETag', '"' . $etag . '"');
    }

    public function store(Request $r)
    {
        $me = $this->me($r);
        if (!$me) {
            return response()->json(['message' => 'Sesi berakhir. Silakan masuk lagi.'], 401);
        }
        $d = $r->validate([
            'jenis' => 'required|in:' . implode(',', self::JENIS),
            'mulai' => 'required|date_format:Y-m-d',
            'selesai' => 'required|date_format:Y-m-d|after_or_equal:mulai',
            'supervisor_id' => 'required|integer',
            'alasan' => 'required|string|max:140',
            'lampiran' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:3072',
        ]);

        $mulai = Carbon::parse($d['mulai'])->startOfDay();
        $selesai = Carbon::parse($d['selesai'])->startOfDay();
        $today = Carbon::today();
        $durasi = $mulai->diffInDays($selesai) + 1;

        $err = null;
        // Anti-Kecurangan Tanggal Lampau: Pengajuan izin/cuti/sakit TIDAK BISA diajukan di hari yang sudah lewat
        if ($mulai->lt($today)) {
            $err = 'Tanggal mulai tidak boleh di hari yang sudah lewat. Pengajuan maksimal dilakukan di hari H (' . $today->format('d/m/Y') . ') atau setelahnya.';
        } elseif ($d['jenis'] === 'cuti' && $durasi > self::MAKS_CUTI_SEKALI) {
            $err = 'Cuti maksimal ' . self::MAKS_CUTI_SEKALI . ' hari sekali pengajuan.';
        } elseif ($durasi > 60) {
            $err = 'Durasi terlalu panjang (maksimal 60 hari).';
        } elseif ($d['jenis'] === 'sakit' && !$r->hasFile('lampiran')) {
            $err = 'Lampirkan foto atau PDF surat sakit resmi dokter.';
        }

        $sup = $this->aktif()->find($d['supervisor_id']);
        if (!$err && (!$sup || $sup->id === $me->id || $sup->departemen !== $me->departemen || !$this->isPengawas($sup))) {
            $err = 'Pengawas tidak sesuai departemen Anda. Pilih ulang pengawas.';
        }
        if (!$err) {
            $bentrok = IzinRequest::where('employee_id', $me->id)->whereIn('status', ['menunggu', 'disetujui'])
                ->whereDate('mulai', '<=', $d['selesai'])->whereDate('selesai', '>=', $d['mulai'])->first();
            if ($bentrok) {
                $err = 'Tanggal ini bertabrakan dengan pengajuan lain (' . $bentrok->mulai->format('d/m') . ' - ' . $bentrok->selesai->format('d/m') . ', ' . $bentrok->status . ').';
            }
        }
        if (!$err && $d['jenis'] === 'cuti') {
            $sisa = $this->sisaCuti($me)['sisa'];
            if ($durasi > $sisa) {
                $err = "Sisa cuti {$sisa} hari, kurang dari {$durasi} hari yang diajukan.";
            }
        }
        if ($err) {
            return response()->json(['message' => $err], 422);
        }

        $path = $r->hasFile('lampiran') ? $r->file('lampiran')->store('izin-lampiran', 'local') : null;

        $p = IzinRequest::create([
            'employee_id' => $me->id,
            'supervisor_id' => $sup->id,
            'jenis' => $d['jenis'],
            'mulai' => $d['mulai'],
            'selesai' => $d['selesai'],
            'durasi' => $durasi,
            'alasan' => trim($d['alasan']),
            'lampiran_path' => $path,
        ]);

        return response()->json($this->reqArr($p->load(['employee', 'supervisor'])), 201);
    }

    public function decide(Request $r, int $id)
    {
        $me = $this->me($r);
        if (!$me) {
            return response()->json(['message' => 'Sesi berakhir. Silakan masuk lagi.'], 401);
        }
        $d = $r->validate(['status' => 'required|in:disetujui,ditolak,menunggu', 'catatan' => 'nullable|string|max:120']);
        $p = IzinRequest::findOrFail($id);

        $myLevel = self::getJobLevel($me->jabatan);
        $empLevel = self::getJobLevel($p->employee?->jabatan);

        // Validasi Otoritas Approval:
        // Case A: Approval Tahap 1 (Foreman/Supervisor)
        // Kasus: Permintaan masih di tahap_1, dan user adalah pengawas yang dituju atau Level 1 berwenang
        $isTahap1 = ($p->current_step ?? 'tahap_1') === 'tahap_1';
        $isTahap2 = ($p->current_step ?? 'tahap_1') === 'tahap_2';

        if ($isTahap1) {
            // Harus cocok id pengawas atau memiliki level > pemohon dan cocok dept/area
            $hasAuth = ($p->supervisor_id === $me->id) || ($myLevel > $empLevel && self::isMatchDeptAndArea($me, $p->employee));
            if (!$hasAuth) {
                return response()->json(['message' => 'Anda tidak memiliki wewenang untuk persetujuan Tahap 1 pada pengajuan ini.'], 403);
            }
        } elseif ($isTahap2) {
            // Harus level 2 ke atas (Superintendent/Deputy/PJO) dan cocok dept/area
            $hasAuth = ($myLevel >= 2) && self::isMatchDeptAndArea($me, $p->employee);
            if (!$hasAuth) {
                return response()->json(['message' => 'Persetujuan Tahap 2 memerlukan wewenang Superintendent / Deputy / PJO.'], 403);
            }
        } else {
            return response()->json(['message' => 'Pengajuan ini sudah selesai diproses.'], 422);
        }

        // 'menunggu' = urungkan keputusan
        if ($d['status'] === 'menunggu') {
            if ($isTahap2) {
                // Kembalikan dari tahap 2 ke tahap 1
                $p->update([
                    'status' => 'menunggu',
                    'current_step' => 'tahap_1',
                    'step_1_status' => null,
                    'step_1_by' => null,
                    'step_1_at' => null,
                    'step_1_catatan' => null,
                ]);
            } else {
                $p->update([
                    'status' => 'menunggu',
                    'current_step' => 'tahap_1',
                    'catatan' => null,
                    'decided_at' => null,
                    'decided_by' => null,
                ]);
            }
            return response()->json($this->reqArr($p->fresh(['employee', 'supervisor', 'step1By', 'step2By'])));
        }

        // Jika DITOLAK di tahap manapun -> Status final langsung DITOLAK
        if ($d['status'] === 'ditolak') {
            $catatan = $d['catatan'] ?? 'Ditolak';
            if ($isTahap1) {
                $p->update([
                    'status' => 'ditolak',
                    'current_step' => 'selesai',
                    'step_1_by' => $me->id,
                    'step_1_at' => now(),
                    'step_1_status' => 'ditolak',
                    'step_1_catatan' => $catatan,
                    'catatan' => "Tahap 1 Ditolak: {$catatan}",
                    'decided_at' => now(),
                    'decided_by' => $me->id,
                ]);
            } else {
                $p->update([
                    'status' => 'ditolak',
                    'current_step' => 'selesai',
                    'step_2_by' => $me->id,
                    'step_2_at' => now(),
                    'step_2_status' => 'ditolak',
                    'step_2_catatan' => $catatan,
                    'catatan' => "Tahap 2 Ditolak: {$catatan}",
                    'decided_at' => now(),
                    'decided_by' => $me->id,
                ]);
            }
            return response()->json($this->reqArr($p->fresh(['employee', 'supervisor', 'step1By', 'step2By'])));
        }

        // Jika DISETUJUI:
        if ($d['status'] === 'disetujui') {
            if ($isTahap1) {
                // Lolos Tahap 1 -> Naik ke Tahap 2 (Status tetap 'menunggu', current_step = 'tahap_2')
                $catatan = $d['catatan'] ?? 'Disetujui oleh Pengawas Lapangan (Tahap 1)';
                $p->update([
                    'step_1_by' => $me->id,
                    'step_1_at' => now(),
                    'step_1_status' => 'disetujui',
                    'step_1_catatan' => $catatan,
                    'current_step' => 'tahap_2',
                    'status' => 'menunggu', // menunggu persetujuan tahap 2
                    'catatan' => 'ACC Tahap 1 Selesai. Menunggu ACC Superintendent / Kabag (Tahap 2).',
                ]);
            } elseif ($isTahap2) {
                // Lolos Tahap 2 -> Status Final DISETUJUI RESMI
                $catatan = $d['catatan'] ?? 'Disetujui Final oleh Superintendent / PJO (Tahap 2)';
                $p->update([
                    'step_2_by' => $me->id,
                    'step_2_at' => now(),
                    'step_2_status' => 'disetujui',
                    'step_2_catatan' => $catatan,
                    'current_step' => 'selesai',
                    'status' => 'disetujui', // Final ACC
                    'catatan' => $catatan,
                    'decided_at' => now(),
                    'decided_by' => $me->id,
                ]);
            }
        }

        return response()->json($this->reqArr($p->fresh(['employee', 'supervisor', 'step1By', 'step2By'])));
    }

    public function cancel(Request $r, int $id)
    {
        $me = $this->me($r);
        if (!$me) {
            return response()->json(['message' => 'Sesi berakhir. Silakan masuk lagi.'], 401);
        }
        $p = IzinRequest::where('employee_id', $me->id)->findOrFail($id);
        if ($p->status !== 'menunggu') {
            return response()->json(['message' => 'Hanya pengajuan yang menunggu yang bisa dibatalkan.'], 422);
        }
        $p->update(['status' => 'dibatalkan']);
        return response()->json($this->reqArr($p->fresh(['employee', 'supervisor'])));
    }

    /** Lampiran hanya untuk pemilik, pengawas tujuan, dan pengawas yang memutuskan. */
    public function lampiran(Request $r, int $id)
    {
        $me = $this->me($r);
        if (!$me) {
            return response()->json(['message' => 'Sesi berakhir. Silakan masuk lagi.'], 401);
        }
        $p = IzinRequest::findOrFail($id);
        if (!in_array($me->id, [$p->employee_id, $p->supervisor_id, $p->decided_by], true)) {
            return response()->json(['message' => 'Tidak berhak mengakses lampiran ini.'], 403);
        }
        if (!$p->lampiran_path || !Storage::disk('local')->exists($p->lampiran_path)) {
            return response()->json(['message' => 'Lampiran tidak ditemukan.'], 404);
        }
        return Storage::disk('local')->response($p->lampiran_path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    public function toggleLokasi(Request $r)
    {
        $me = $this->me($r);
        if (!$me || !$this->isPengawas($me)) {
            return response()->json(['message' => 'Hanya untuk pengawas.'], 403);
        }
        $me->forceFill(['dilokasi' => !$me->dilokasi])->save();
        return response()->json(['dilokasi' => (bool) $me->dilokasi]);
    }
}

