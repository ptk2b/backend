<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\IzinRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AdminIzinLapanganController extends Controller
{
    private function serializeItem(IzinRequest $p): array
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
            'has_lampiran' => !empty($p->lampiran_path),
            'diajukan' => $p->created_at?->toIso8601String(),
            'diputuskan' => $p->decided_at?->toIso8601String(),
            'karyawan' => $p->employee ? [
                'id' => $p->employee->id,
                'nama' => $p->employee->nama_lengkap,
                'nip' => $p->employee->nip,
                'nik' => $p->employee->nik,
                'jabatan' => $p->employee->jabatan,
                'departemen' => $p->employee->departemen,
            ] : null,
            'pengawas' => $p->supervisor ? [
                'id' => $p->supervisor->id,
                'nama' => $p->supervisor->nama_lengkap,
                'jabatan' => $p->supervisor->jabatan,
                'departemen' => $p->supervisor->departemen,
            ] : null,
            'diputuskan_oleh' => $p->decider?->nama_lengkap,
        ];
    }

    public function dashboard(Request $r)
    {
        $year = (int) $r->input('year', now()->year);
        $month = (int) $r->input('month', now()->month);

        $q = IzinRequest::query();

        $totalMenunggu = (clone $q)->where('status', 'menunggu')->count();
        $totalDisetujui = (clone $q)->where('status', 'disetujui')->whereYear('mulai', $year)->whereMonth('mulai', $month)->count();
        $totalDitolak = (clone $q)->where('status', 'ditolak')->whereYear('mulai', $year)->whereMonth('mulai', $month)->count();
        $totalHariBulanIni = (clone $q)->where('status', 'disetujui')->whereYear('mulai', $year)->whereMonth('mulai', $month)->sum('durasi');

        // Breakdown per departemen
        $deptCounts = IzinRequest::join('employees', 'employees.id', '=', 'izin_requests.employee_id')
            ->where('izin_requests.status', 'disetujui')
            ->whereYear('izin_requests.mulai', $year)
            ->whereMonth('izin_requests.mulai', $month)
            ->selectRaw('employees.departemen, count(*) as total, sum(izin_requests.durasi) as total_hari')
            ->groupBy('employees.departemen')
            ->get();

        // Daftar departemen yang tersedia untuk filter
        $departments = Employee::whereNotNull('departemen')->where('departemen', '!=', '')
            ->distinct()->pluck('departemen')->sort()->values();

        // Daftar pengawas lapangan aktif beserta status dilokasi
        $supervisors = Employee::where('status_karyawan', 'ACTIVE')
            ->where(function ($q) {
                $q->where('jabatan', 'like', '%FOREMAN%')
                  ->orWhere('jabatan', 'like', '%SUPERVISOR%')
                  ->orWhere('jabatan', 'like', '%PENGAWAS%')
                  ->orWhere('jabatan', 'like', '%LEADER%')
                  ->orWhere('jabatan', 'like', '%SUPERINTENDENT%')
                  ->orWhere('jabatan', 'like', '%MANAGER%');
            })
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap as nama', 'departemen', 'dilokasi']);

        // Daftar karyawan aktif untuk bantuan input admin
        $employees = Employee::where('status_karyawan', 'ACTIVE')
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap as nama', 'nip', 'nik', 'jabatan', 'departemen']);

        return response()->json([
            'year' => $year,
            'month' => $month,
            'summary' => [
                'menunggu' => $totalMenunggu,
                'disetujui_bulan_ini' => $totalDisetujui,
                'ditolak_bulan_ini' => $totalDitolak,
                'total_hari_cuti_bulan_ini' => $totalHariBulanIni,
            ],
            'departemen_stats' => $deptCounts,
            'departments' => $departments,
            'supervisors' => $supervisors,
            'employees' => $employees,
        ]);
    }

    public function index(Request $r)
    {
        $q = IzinRequest::with(['employee', 'supervisor', 'decider'])->latest();

        if ($r->filled('status') && $r->status !== 'semua') {
            $q->where('status', $r->status);
        }

        if ($r->filled('jenis') && $r->jenis !== 'semua') {
            $q->where('jenis', $r->jenis);
        }

        if ($r->filled('departemen') && $r->departemen !== 'semua') {
            $q->whereHas('employee', function ($eq) use ($r) {
                $eq->where('departemen', $r->departemen);
            });
        }

        if ($r->filled('search')) {
            $term = trim($r->search);
            $q->where(function ($sq) use ($term) {
                $sq->where('alasan', 'like', "%{$term}%")
                   ->orWhereHas('employee', function ($eq) use ($term) {
                       $eq->where('nama_lengkap', 'like', "%{$term}%")
                          ->orWhere('nip', 'like', "%{$term}%")
                          ->orWhere('nik', 'like', "%{$term}%");
                   });
            });
        }

        if ($r->filled('year')) {
            $q->whereYear('mulai', $r->year);
        }
        if ($r->filled('month')) {
            $q->whereMonth('mulai', $r->month);
        }

        $perPage = (int) $r->input('per_page', 20);
        $paginated = $q->paginate($perPage);

        return response()->json([
            'data' => collect($paginated->items())->map(fn ($it) => $this->serializeItem($it)),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'per_page' => $paginated->perPage(),
        ]);
    }

    public function override(Request $r, int $id)
    {
        $d = $r->validate([
            'status' => 'required|in:disetujui,ditolak,menunggu,dibatalkan',
            'catatan' => 'nullable|string|max:200',
            'supervisor_id' => 'nullable|exists:employees,id',
        ]);

        $item = IzinRequest::findOrFail($id);

        $payload = [
            'status' => $d['status'],
            'catatan' => $d['catatan'] ?? $item->catatan,
        ];

        if (!empty($d['supervisor_id'])) {
            $payload['supervisor_id'] = $d['supervisor_id'];
        }

        if ($d['status'] === 'menunggu') {
            $payload['decided_at'] = null;
            $payload['decided_by'] = null;
        } else {
            $payload['decided_at'] = now();
            // Jika ada user admin yang login
            $payload['decided_by'] = $item->supervisor_id;
        }

        $item->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Status pengajuan berhasil diperbarui oleh Administrator.',
            'data' => $this->serializeItem($item->fresh(['employee', 'supervisor', 'decider'])),
        ]);
    }

    public function storeByAdmin(Request $r)
    {
        $d = $r->validate([
            'employee_id' => 'required|exists:employees,id',
            'supervisor_id' => 'required|exists:employees,id',
            'jenis' => 'required|in:cuti,izin,sakit',
            'mulai' => 'required|date_format:Y-m-d',
            'selesai' => 'required|date_format:Y-m-d|after_or_equal:mulai',
            'alasan' => 'required|string|max:200',
            'status' => 'required|in:menunggu,disetujui',
            'lampiran' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'catatan' => 'nullable|string|max:200',
        ]);

        $employee = Employee::findOrFail($d['employee_id']);
        $supervisor = Employee::findOrFail($d['supervisor_id']);

        $mulai = Carbon::parse($d['mulai']);
        $selesai = Carbon::parse($d['selesai']);
        $durasi = $mulai->diffInDays($selesai) + 1;

        $path = $r->hasFile('lampiran') ? $r->file('lampiran')->store('izin-lampiran', 'local') : null;

        $p = IzinRequest::create([
            'employee_id' => $employee->id,
            'supervisor_id' => $supervisor->id,
            'jenis' => $d['jenis'],
            'mulai' => $d['mulai'],
            'selesai' => $d['selesai'],
            'durasi' => $durasi,
            'alasan' => trim($d['alasan']),
            'status' => $d['status'],
            'catatan' => $d['catatan'] ?? ($d['status'] === 'disetujui' ? 'Diinput & disetujui langsung oleh Admin Area' : 'Diinput via Admin Area'),
            'lampiran_path' => $path,
            'decided_at' => $d['status'] === 'disetujui' ? now() : null,
            'decided_by' => $d['status'] === 'disetujui' ? $supervisor->id : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin karyawan berhasil dicatat oleh Admin.',
            'data' => $this->serializeItem($p->load(['employee', 'supervisor'])),
        ], 201);
    }

    public function calendar(Request $r)
    {
        $year = (int) $r->input('year', now()->year);
        $month = (int) $r->input('month', now()->month);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $items = IzinRequest::with(['employee'])
            ->whereIn('status', ['disetujui', 'menunggu'])
            ->whereDate('mulai', '<=', $end->toDateString())
            ->whereDate('selesai', '>=', $start->toDateString())
            ->get();

        $calendar = [];
        foreach ($items as $it) {
            $itStart = Carbon::parse($it->mulai)->max($start);
            $itEnd = Carbon::parse($it->selesai)->min($end);

            $curr = clone $itStart;
            while ($curr->lte($itEnd)) {
                $ds = $curr->toDateString();
                $calendar[$ds][] = [
                    'id' => $it->id,
                    'jenis' => $it->jenis,
                    'status' => $it->status,
                    'karyawan' => $it->employee?->nama_lengkap,
                    'departemen' => $it->employee?->departemen,
                    'jabatan' => $it->employee?->jabatan,
                ];
                $curr->addDay();
            }
        }

        return response()->json([
            'year' => $year,
            'month' => $month,
            'events' => $calendar,
        ]);
    }

    public function export(Request $r)
    {
        $q = IzinRequest::with(['employee', 'supervisor', 'decider'])->latest();

        if ($r->filled('status') && $r->status !== 'semua') {
            $q->where('status', $r->status);
        }
        if ($r->filled('departemen') && $r->departemen !== 'semua') {
            $q->whereHas('employee', fn($eq) => $eq->where('departemen', $r->departemen));
        }
        if ($r->filled('year')) {
            $q->whereYear('mulai', $r->year);
        }
        if ($r->filled('month')) {
            $q->whereMonth('mulai', $r->month);
        }

        $rows = $q->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rekap_izin_lapangan_' . date('Ymd_His') . '.csv"',
        ];

        $callback = function () use ($rows) {
            $fh = fopen('php://output', 'w');
            // UTF-8 BOM agar Excel membukanya dengan karakter yang tepat
            fputs($fh, "\xEF\xBB\xBF");

            fputcsv($fh, [
                'ID', 'Nama Karyawan', 'NIP', 'Departemen', 'Jabatan',
                'Jenis', 'Mulai', 'Selesai', 'Durasi (Hari)', 'Alasan',
                'Status', 'Pengawas', 'Catatan', 'Tanggal Pengajuan', 'Tanggal Keputusan'
            ]);

            foreach ($rows as $r) {
                fputcsv($fh, [
                    $r->id,
                    $r->employee?->nama_lengkap ?? '-',
                    $r->employee?->nip ?? '-',
                    $r->employee?->departemen ?? '-',
                    $r->employee?->jabatan ?? '-',
                    strtoupper($r->jenis),
                    $r->mulai->toDateString(),
                    $r->selesai->toDateString(),
                    $r->durasi,
                    $r->alasan,
                    ucfirst($r->status),
                    $r->supervisor?->nama_lengkap ?? '-',
                    $r->catatan ?? '-',
                    $r->created_at?->format('Y-m-d H:i:s'),
                    $r->decided_at?->format('Y-m-d H:i:s') ?? '-',
                ]);
            }
            fclose($fh);
        };

        return Response::stream($callback, 200, $headers);
    }
}
