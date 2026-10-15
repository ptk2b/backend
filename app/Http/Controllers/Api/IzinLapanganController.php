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
    private const RE_PENGAWAS = '/\bOFFICER\b|\bLEADER\b|\bKOORDINATOR\b|PENANGGUNG\s+JAWAB|SUPERINTENDENT|FOREMAN|SUPERVISOR/i';

    private function norm(?string $s): string
    {
        return Str::lower(trim(preg_replace('/\s+/', ' ', (string) $s)));
    }

    private function isPengawas(Employee $e): bool
    {
        return (bool) preg_match(self::RE_PENGAWAS, (string) $e->jabatan);
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
            'lampiran' => $p->lampiran_path ? url('api/public/izin/requests/' . $p->id . '/lampiran') : null,
            'diajukan' => $p->created_at?->toIso8601String(),
            'diputuskan' => $p->decided_at?->toIso8601String(),
            'karyawan' => $p->employee ? [
                'nama' => $p->employee->nama_lengkap,
                'nip' => $p->employee->nip,
                'jabatan' => $p->employee->jabatan,
                'departemen' => $p->employee->departemen,
            ] : null,
            'pengawas' => $p->supervisor?->nama_lengkap,
            'pengawas_id' => $p->supervisor_id,
            'diputuskan_oleh' => $p->decider?->nama_lengkap,
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
        // Batas percobaan per NIP + IP: 5 gagal per 10 menit, tanpa memblokir karyawan lain di jaringan yang sama.
        $key = 'izin-login:' . Str::lower($no) . '|' . $r->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $m = (int) ceil(RateLimiter::availableIn($key) / 60);
            return response()->json(['message' => "Terlalu banyak percobaan. Coba lagi dalam {$m} menit."], 429);
        }
        $e = $this->aktif()->where(fn ($q) => $q->where('nip', $no)->orWhere('nik', $no))
            ->get()->first(fn ($x) => $this->norm($x->nama_lengkap) === $this->norm($d['nama']));
        if (!$e) {
            RateLimiter::hit($key, 600);
            return response()->json(['message' => 'Nama atau NIP tidak cocok dengan data HR.'], 422);
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

        $pengawas = $this->aktif()->where('departemen', $me->departemen)->where('id', '!=', $me->id)->get()
            ->filter(fn ($e) => $this->isPengawas($e))
            ->sortByDesc(fn ($e) => (int) $e->dilokasi)->values()
            ->map(fn ($e) => $this->userArr($e));

        $own = IzinRequest::where('employee_id', $me->id)
            ->latest()->limit(50)->get()->map(fn ($p) => $this->reqArr($p));

        $out = ['user' => $this->userArr($me), 'pengawas' => $pengawas, 'riwayat' => $own, 'sisa_cuti' => $this->sisaCuti($me)];

        if ($isPengawas) {
            $q = IzinRequest::where('supervisor_id', $me->id);
            $out['menunggu'] = (clone $q)->where('status', 'menunggu')->oldest()->get()->map(fn ($p) => $this->reqArr($p));
            $out['keputusan'] = (clone $q)->whereIn('status', ['disetujui', 'ditolak'])
                ->latest('decided_at')->limit(50)->get()->map(fn ($p) => $this->reqArr($p));
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

        $mulai = Carbon::parse($d['mulai']);
        $selesai = Carbon::parse($d['selesai']);
        $durasi = $mulai->diffInDays($selesai) + 1;

        $err = null;
        if ($d['jenis'] === 'cuti' && $mulai->lt(today())) {
            $err = 'Cuti harus dimulai hari ini atau setelahnya.';
        } elseif ($mulai->lt(today()->subDays(7))) {
            $err = 'Pengajuan mundur maksimal 7 hari.';
        } elseif ($d['jenis'] === 'cuti' && $durasi > self::MAKS_CUTI_SEKALI) {
            $err = 'Cuti maksimal ' . self::MAKS_CUTI_SEKALI . ' hari sekali pengajuan.';
        } elseif ($durasi > 60) {
            $err = 'Durasi terlalu panjang.';
        } elseif ($d['jenis'] === 'sakit' && !$r->hasFile('lampiran')) {
            $err = 'Lampirkan foto atau PDF surat sakit.';
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
        if ($p->supervisor_id !== $me->id) {
            return response()->json(['message' => 'Pengajuan ini bukan untuk Anda.'], 403);
        }
        // 'menunggu' = urungkan keputusan; selain itu hanya dari status menunggu.
        if ($d['status'] === 'menunggu') {
            if (!in_array($p->status, ['disetujui', 'ditolak'], true)) {
                return response()->json(['message' => 'Tidak ada keputusan yang bisa diurungkan.'], 422);
            }
            $p->update(['status' => 'menunggu', 'catatan' => null, 'decided_at' => null, 'decided_by' => null]);
        } else {
            if ($p->status !== 'menunggu') {
                return response()->json(['message' => 'Pengajuan ini sudah diproses.'], 422);
            }
            $p->update(['status' => $d['status'], 'catatan' => $d['catatan'] ?? null, 'decided_at' => now(), 'decided_by' => $me->id]);
        }
        return response()->json($this->reqArr($p->fresh(['employee', 'supervisor'])));
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

