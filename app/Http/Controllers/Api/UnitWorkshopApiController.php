<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UnitWorkshop;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UnitWorkshopApiController extends Controller
{
    /**
     * Daftar data unit (admin) + ringkasan per area.
     */
    public function index(Request $request): JsonResponse
    {
        $query = UnitWorkshop::query()->orderBy('area')->orderBy('id');

        if ($area = $request->query('area')) {
            $query->where('area', $area);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('no_unit', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('chassis_no', 'like', "%{$search}%")
                    ->orWhere('engine_no', 'like', "%{$search}%")
                    ->orWhere('trouble', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('per_page', 100), 1), 500);

        return response()->json([
            'units' => $query->paginate($perPage),
            'areas' => UnitWorkshop::areaSummary(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Impor data hasil baca Excel/CSV (dibaca di browser, dikirim sebagai JSON).
     * dry_run=true hanya menghitung perubahan tanpa menyimpan.
     * Gabung/perbarui berdasarkan No Unit. Unit tidak pernah dihapus.
     */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows' => 'required|array|min:1|max:2000',
            'rows.*' => 'array',
            'dry_run' => 'sometimes|boolean',
        ]);
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $errors = [];
        $prepared = [];
        foreach ($data['rows'] as $i => $row) {
            $line = $i + 1;
            $noUnit = strtoupper($this->clean($row['no_unit'] ?? '', 50));
            // Area diprioritaskan dari kolom job_location, lalu fallback ke area / nama sheet
            $area = $this->normalizeArea($row['job_location'] ?? '') 
                 ?: $this->normalizeArea($row['area'] ?? '');

            if ($noUnit === '') {
                $errors[] = "Baris {$line}: No Unit kosong.";
                continue;
            }
            if (!$area) {
                $errors[] = "Baris {$line} (unit {$noUnit}): Job Location / Area tidak dikenal atau kosong.";
                continue;
            }

            $item = ['area' => $area, 'no_unit' => $noUnit];
            foreach (UnitWorkshop::IMPORT_FIELDS as $field) {
                $maxLen = match ($field) {
                    'trouble', 'keterangan', 'waiting_part' => 500,
                    'tahun' => 10,
                    'severity', 'status', 'breakdown_time' => 20,
                    default => 100,
                };
                $item[$field] = $this->clean($row[$field] ?? '', $maxLen) ?: null;
            }
            $item['breakdown_date'] = $this->normalizeDate($item['breakdown_date']);
            // Job Location diseragamkan dengan nama resmi area jika belum spesifik
            $item['job_location'] = UnitWorkshop::AREAS[$area] ?? ($this->clean($row['job_location'] ?? '', 100) ?: null);
            $item['severity'] = $item['severity'] ? strtoupper($item['severity']) : null;
            $item['status'] = $this->normalizeStatus($item['status']);

            $prepared[$noUnit] = $item; // duplikat No Unit di file: baris terakhir dipakai
        }

        $existing = UnitWorkshop::whereIn('no_unit', array_keys($prepared))->get()->keyBy('no_unit');

        $created = $updated = $unchanged = 0;
        $changes = [];

        DB::transaction(function () use ($prepared, $existing, $dryRun, &$created, &$updated, &$unchanged, &$changes) {
            foreach ($prepared as $noUnit => $item) {
                $current = $existing->get($noUnit);

                if (!$current) {
                    $created++;
                    $changes[] = ['no_unit' => $noUnit, 'type' => 'new'];
                    if (!$dryRun) {
                        UnitWorkshop::create($item);
                    }
                    continue;
                }

                $diff = [];
                foreach ($item as $field => $value) {
                    $old = $field === 'breakdown_date'
                        ? $current->breakdown_date?->format('Y-m-d')
                        : $current->{$field};
                    if ((string) $old !== (string) $value) {
                        $diff[] = $field;
                    }
                }

                if (!$diff) {
                    $unchanged++;
                    continue;
                }

                $updated++;
                $changes[] = ['no_unit' => $noUnit, 'type' => 'update', 'fields' => $diff];
                if (!$dryRun) {
                    $current->update($item);
                }
            }
        });

        if (!$dryRun) {
            $this->syncToRfuRecords();
        }

        return response()->json([
            'message' => $dryRun ? 'Pratinjau impor.' : 'Impor data unit berhasil dan disinkronkan ke Laporan RFU.',
            'dry_run' => $dryRun,
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'errors' => $errors,
            'changes' => array_slice($changes, 0, 200),
        ]);
    }

    /**
     * Sinkronisasikan unit workshop yang belum RFU (MAJOR / W/P) ke rfu_unit_records
     * agar langsung otomatis muncul di Laporan RFU Unit & Monitoring RFU.
     */
    public function syncToRfuRecords(): JsonResponse
    {
        $locations = \App\Models\RfuLocation::all()->keyBy('code');
        if ($locations->isEmpty()) {
            return response()->json(['message' => 'Lokasi RFU belum tersedia.'], 400);
        }

        $workshopUnits = UnitWorkshop::all();
        $syncedCount = 0;

        DB::transaction(function () use ($locations, $workshopUnits, &$syncedCount) {
            foreach ($workshopUnits as $unit) {
                $loc = $locations->get($unit->area);
                if (!$loc) continue;

                $isBreakdown = ($unit->status !== 'RFU');
                $existingRecord = \App\Models\RfuUnitRecord::where('dt_number', $unit->no_unit)->first();

                if ($isBreakdown) {
                    // Unit sedang breakdown / perbaikan workshop (MAJOR / W/P)
                    $data = [
                        'location_id' => $loc->id,
                        'position' => $unit->job_location ?: 'Workshop',
                        'tanggal_masuk' => $unit->breakdown_date ? $unit->breakdown_date->format('Y-m-d') : date('Y-m-d'),
                        'jam_masuk' => $unit->breakdown_time ?: '08:00',
                        'driver_complaint' => $unit->trouble ?: 'Perbaikan Workshop (' . $unit->status . ')',
                        'estimation_type' => $unit->waiting_part ? 'waiting_part' : ($unit->severity === 'MAJOR' ? 'overhaul' : 'datetime'),
                        'estimation_note' => $unit->waiting_part 
                            ? substr('Waiting Part: ' . $unit->waiting_part, 0, 250) 
                            : ($unit->keterangan ? substr($unit->keterangan, 0, 250) : null),
                        'status' => 'ON_PROGRESS',
                        'completed_at' => null,
                    ];

                    if ($existingRecord) {
                        $existingRecord->update($data);
                    } else {
                        $data['dt_number'] = $unit->no_unit;
                        \App\Models\RfuUnitRecord::create($data);
                    }
                    $syncedCount++;
                } else {
                    // Unit berstatus RFU: jika sebelumnya tercatat ON_PROGRESS, tandai selesai
                    if ($existingRecord && $existingRecord->status === 'ON_PROGRESS') {
                        $existingRecord->update([
                            'status' => 'RFU',
                            'completed_at' => Carbon::now(),
                        ]);
                    }
                }
            }

            // Sinkronisasi counter on_progress_count per lokasi
            foreach ($locations as $loc) {
                $count = \App\Models\RfuUnitRecord::where('location_id', $loc->id)
                    ->where('status', 'ON_PROGRESS')
                    ->count();
                $loc->update(['on_progress_count' => $count]);
            }
        });

        return response()->json([
            'message' => "Berhasil menyinkronkan {$syncedCount} unit breakdown ke Laporan RFU Unit.",
            'synced_count' => $syncedCount,
        ]);
    }

    private function clean(mixed $value, int $maxLen = 500): string
    {
        if ($value === null) {
            return '';
        }
        $str = is_scalar($value) ? (string) $value : '';
        $clean = trim(strip_tags($str));
        return $maxLen > 0 ? substr($clean, 0, $maxLen) : $clean;
    }

    private function normalizeArea(mixed $value): ?string
    {
        $key = preg_replace('/[^a-z]/', '', strtolower((string) $value));
        if (str_contains($key, 'petrosea')) return 'petrosea';
        if (str_contains($key, 'pama')) return 'pama';
        if (str_contains($key, 'sims')) return 'sims';
        if (str_contains($key, 'paku')) return 'spaku';
        return null;
    }

    /** Status kosong dianggap RFU. */
    private function normalizeStatus(?string $value): string
    {
        $v = strtoupper(trim((string) $value));
        if ($v === '' || $v === 'READY') {
            return 'RFU';
        }
        if (str_contains($v, 'W/P') || $v === 'WP' || str_contains($v, 'WAITING')) {
            return 'W/P';
        }
        return substr($v, 0, 10);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }
        // Tangani tanggal serial numerik dari Excel (contoh: 45000 s/d 65000)
        if (is_numeric($value) && (int) $value > 30000 && (int) $value < 70000) {
            try {
                return Carbon::createFromTimestampUTC(((int) $value - 25569) * 86400)->format('Y-m-d');
            } catch (\Throwable) {}
        }
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
