<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RfuLocation;
use App\Models\RfuUnitRecord;
use App\Models\UnitWorkshop;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RfuApiController extends Controller
{
    /**
     * Ensure default 4 locations exist (self-healing for fresh/unseeded databases)
     */
    private function ensureDefaultLocationsExist(): void
    {
        if (RfuLocation::count() === 0) {
            $defaultLocations = [
                ['name' => 'Petrosea', 'code' => 'petrosea', 'rfu_count' => 45, 'plan_count' => 60, 'sort_order' => 1],
                ['name' => 'Pama', 'code' => 'pama', 'rfu_count' => 14, 'plan_count' => 12, 'sort_order' => 2],
                ['name' => 'SIMS', 'code' => 'sims', 'rfu_count' => 5, 'plan_count' => 12, 'sort_order' => 3],
                ['name' => 'S. Paku', 'code' => 'spaku', 'rfu_count' => 10, 'plan_count' => 10, 'sort_order' => 4],
            ];
            foreach ($defaultLocations as $loc) {
                RfuLocation::firstOrCreate(['code' => $loc['code']], $loc);
            }
        }
    }

    /**
     * Timpa rfu_count & plan_count dengan hitungan otomatis dari data unit workshop
     * (hanya untuk area yang sudah punya data impor; area lain tetap angka manual).
     */
    private function applyWorkshopCounts($locations)
    {
        try {
            if (!Schema::hasTable('unit_workshops')) {
                return $locations;
            }
            $summary = UnitWorkshop::areaSummary();
            foreach ($locations as $loc) {
                $s = $summary[$loc->code] ?? null;
                if ($s && $s['total'] > 0) {
                    $loc->rfu_count = $s['rfu'];
                    $loc->plan_count = $s['total'];
                }
            }
        } catch (\Throwable $e) {
            // Graceful fallback if table not yet migrated
        }
        return $locations;
    }

    /**
     * Dapatkan timestamp waktu terakhir perubahan data baik di:
     * 1. rfu_unit_records (tambah, edit, status RFU, hapus)
     * 2. rfu_locations (edit counter/target RFU & Plan)
     * 3. unit_workshops (impor data unit workshop & sinkronisasi)
     */
    private function getLatestDataUpdatedAt(): Carbon
    {
        $timestamps = [];

        try {
            $latestUnit = RfuUnitRecord::max('updated_at');
            if ($latestUnit) {
                $timestamps[] = Carbon::parse($latestUnit)->timezone('Asia/Makassar');
            }
        } catch (\Throwable $e) {}

        try {
            $latestLoc = RfuLocation::max('updated_at');
            if ($latestLoc) {
                $timestamps[] = Carbon::parse($latestLoc)->timezone('Asia/Makassar');
            }
        } catch (\Throwable $e) {}

        try {
            if (Schema::hasTable('unit_workshops')) {
                $latestWorkshop = UnitWorkshop::max('updated_at');
                if ($latestWorkshop) {
                    $timestamps[] = Carbon::parse($latestWorkshop)->timezone('Asia/Makassar');
                }
            }
        } catch (\Throwable $e) {}

        if (!empty($timestamps)) {
            usort($timestamps, fn($a, $b) => $b->getTimestamp() <=> $a->getTimestamp());
            return $timestamps[0];
        }

        return Carbon::now('Asia/Makassar');
    }

    /**
     * Public endpoint untuk halaman Owner (tanpa login)
     */
    public function publicDashboard(Request $request): JsonResponse
    {
        $this->ensureDefaultLocationsExist();

        $locations = $this->applyWorkshopCounts(
            RfuLocation::with(['activeUnits'])
                ->orderBy('sort_order', 'asc')
                ->get()
        );

        $totalRfu = $locations->sum('rfu_count');
        $totalPlan = $locations->sum('plan_count');
        $totalOnProgress = $locations->sum(function ($loc) {
            return $loc->activeUnits->count();
        });

        $readinessPercentage = $totalPlan > 0
            ? round(($totalRfu / $totalPlan) * 100, 1)
            : 0;

        $now = Carbon::now('Asia/Makassar');
        $latestUpdated = $this->getLatestDataUpdatedAt();

        $dtNumbers = $locations->flatMap(function ($loc) {
            return $loc->activeUnits->pluck('dt_number');
        })->unique()->filter()->values();
        
        $workshopMap = collect();
        try {
            if (Schema::hasTable('unit_workshops') && $dtNumbers->isNotEmpty()) {
                $workshopMap = UnitWorkshop::whereIn('no_unit', $dtNumbers)->get()->keyBy('no_unit');
            }
        } catch (\Throwable $e) {
            // Graceful fallback if table not yet migrated
        }

        return response()->json([
            'report_time' => $latestUpdated->format('H:i'),
            'report_date' => $latestUpdated->translatedFormat('d F Y'),
            'timestamp' => $now->toIso8601String(),
            'last_updated_at' => $latestUpdated->toIso8601String(),
            'summary' => [
                'total_rfu' => $totalRfu,
                'total_plan' => $totalPlan,
                'total_on_progress' => $totalOnProgress,
                'readiness_percentage' => $readinessPercentage,
            ],
            'locations' => $locations->map(function ($loc) use ($workshopMap) {
                return [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'code' => $loc->code,
                    'rfu_count' => (int)$loc->rfu_count,
                    'plan_count' => (int)$loc->plan_count,
                    'on_progress_count' => $loc->activeUnits->count(),
                    'active_units_count' => $loc->activeUnits->count(),
                    'units' => $loc->activeUnits->map(function ($u) use ($workshopMap) {
                        $w = $workshopMap->get($u->dt_number);
                        $arr = $u->toArray();
                        $arr['merk'] = $w?->merk;
                        $arr['type'] = $w?->type;
                        $arr['chassis_no'] = $w?->chassis_no;
                        $arr['engine_no'] = $w?->engine_no;
                        $arr['job'] = $w?->job;
                        $arr['tahun'] = $w?->tahun;
                        $arr['severity'] = $w?->severity;
                        $arr['keterangan'] = $w?->keterangan;
                        $arr['waiting_part'] = $w?->waiting_part;
                        $arr['job_location'] = $w?->job_location ?: $u->position;
                        $arr['ready'] = $w?->ready;
                        return $arr;
                    }),
                ];
            }),
        ])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
    }

    /**
     * Admin Index - get units with filters (status, location, search)
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $this->ensureDefaultLocationsExist();

        $status = $request->query('status', 'ON_PROGRESS');
        $locationId = $request->query('location_id');
        $search = $request->query('search');

        $locations = $this->applyWorkshopCounts(
            RfuLocation::withCount(['activeUnits'])->orderBy('sort_order', 'asc')->get()
        )
            ->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'code' => $loc->code,
                    'rfu_count' => (int)$loc->rfu_count,
                    'plan_count' => (int)$loc->plan_count,
                    'on_progress_count' => (int)$loc->active_units_count,
                    'active_units_count' => (int)$loc->active_units_count,
                    'sort_order' => $loc->sort_order,
                ];
            });

        $query = RfuUnitRecord::with('location')->orderBy('id', 'desc');

        if ($status !== 'ALL') {
            $query->where('status', $status);
        }

        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('dt_number', 'like', "%{$search}%")
                  ->orWhere('position', 'like', "%{$search}%")
                  ->orWhere('driver_complaint', 'like', "%{$search}%")
                  ->orWhere('estimation_note', 'like', "%{$search}%");
            });
        }

        $units = $query->paginate($request->query('per_page', 50));

        $dtNumbers = collect($units->items())->pluck('dt_number')->unique()->filter()->values();
        $workshopMap = collect();
        try {
            if (Schema::hasTable('unit_workshops') && $dtNumbers->isNotEmpty()) {
                $workshopMap = UnitWorkshop::whereIn('no_unit', $dtNumbers)->get()->keyBy('no_unit');
            }
        } catch (\Throwable $e) {}

        if ($workshopMap->isNotEmpty()) {
            foreach ($units->items() as $u) {
                $w = $workshopMap->get($u->dt_number);
                if ($w) {
                    $u->severity = $w->severity;
                    $u->merk = $w->merk;
                    $u->type = $w->type;
                }
            }
        }

        $totalRfu = $locations->sum('rfu_count');
        $totalPlan = $locations->sum('plan_count');
        $latestUpdated = $this->getLatestDataUpdatedAt();

        return response()->json([
            'locations' => $locations,
            'units' => $units,
            'report_time' => $latestUpdated->format('H:i'),
            'report_date' => $latestUpdated->translatedFormat('d F Y'),
            'last_updated_at' => $latestUpdated->toIso8601String(),
            'summary' => [
                'total_rfu' => $totalRfu,
                'total_plan' => $totalPlan,
                'total_on_progress' => $totalActive,
            ]
        ])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
    }

    /**
     * Admin: Store Unit baru
     */
    public function storeUnit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => 'required|exists:rfu_locations,id',
            'position' => 'required|string|max:50',
            'tanggal_masuk' => 'required|date',
            'jam_masuk' => 'required|string|max:10|regex:/^[0-9]{1,2}:[0-9]{2}$/',
            'dt_number' => 'required|string|max:50',
            'driver_complaint' => 'required|string|max:2000',
            'estimation_type' => 'required|in:datetime,waiting_part,waiting_bay,overhaul,other',
            'estimation_datetime' => 'nullable|date',
            'estimation_note' => 'nullable|string|max:255',
            'status' => 'nullable|in:ON_PROGRESS,RFU',
        ]);

        $validated['dt_number'] = strtoupper(trim(strip_tags($validated['dt_number'])));
        $validated['position'] = trim(strip_tags($validated['position']));
        $validated['driver_complaint'] = trim(strip_tags($validated['driver_complaint']));
        if (isset($validated['estimation_note'])) {
            $validated['estimation_note'] = trim(strip_tags($validated['estimation_note']));
        }

        $validated['status'] = $validated['status'] ?? 'ON_PROGRESS';
        $validated['created_by'] = $request->user()?->id;

        $unit = \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
            $created = RfuUnitRecord::create($validated);
            $location = RfuLocation::find($validated['location_id']);
            if ($location) {
                $location->touch();
            }
            $this->syncLocationCount($validated['location_id']);
            return $created;
        });

        return response()->json([
            'message' => 'Unit berhasil ditambahkan.',
            'unit' => $unit->load('location'),
        ], 201);
    }

    /**
     * Admin: Update Unit
     */
    public function updateUnit(Request $request, int $id): JsonResponse
    {
        $unit = RfuUnitRecord::findOrFail($id);

        $validated = $request->validate([
            'location_id' => 'sometimes|required|exists:rfu_locations,id',
            'position' => 'sometimes|required|string|max:50',
            'tanggal_masuk' => 'sometimes|required|date',
            'jam_masuk' => 'sometimes|required|string|max:10|regex:/^[0-9]{1,2}:[0-9]{2}$/',
            'dt_number' => 'sometimes|required|string|max:50',
            'driver_complaint' => 'sometimes|required|string|max:2000',
            'estimation_type' => 'sometimes|required|in:datetime,waiting_part,waiting_bay,overhaul,other',
            'estimation_datetime' => 'nullable|date',
            'estimation_note' => 'nullable|string|max:255',
            'status' => 'sometimes|required|in:ON_PROGRESS,RFU',
        ]);

        if (isset($validated['dt_number'])) {
            $validated['dt_number'] = strtoupper(trim(strip_tags($validated['dt_number'])));
        }
        if (isset($validated['position'])) {
            $validated['position'] = trim(strip_tags($validated['position']));
        }
        if (isset($validated['driver_complaint'])) {
            $validated['driver_complaint'] = trim(strip_tags($validated['driver_complaint']));
        }
        if (isset($validated['estimation_note'])) {
            $validated['estimation_note'] = trim(strip_tags($validated['estimation_note']));
        }

        $oldLocationId = $unit->location_id;
        $oldStatus = $unit->status;

        if (isset($validated['status']) && $validated['status'] === 'RFU' && $oldStatus !== 'RFU') {
            $validated['completed_at'] = Carbon::now();
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($unit, $validated, $oldLocationId) {
            $unit->update($validated);
            $this->syncLocationCount($unit->location_id);
            if ($oldLocationId !== $unit->location_id) {
                $this->syncLocationCount($oldLocationId);
            }
        });

        return response()->json([
            'message' => 'Unit berhasil diperbarui.',
            'unit' => $unit->load('location'),
        ]);
    }

    /**
     * Admin: Tandai Selesai / Jadikan RFU
     */
    public function markAsRfu(Request $request, int $id): JsonResponse
    {
        $unit = RfuUnitRecord::findOrFail($id);
        $incrementRfuCount = $request->boolean('increment_location_rfu', true);

        $unit->update([
            'status' => 'RFU',
            'completed_at' => Carbon::now(),
        ]);

        $location = RfuLocation::find($unit->location_id);
        if ($location) {
            if ($incrementRfuCount) {
                $location->increment('rfu_count');
            }
            $location->touch();
            $this->syncLocationCount($location->id);
        }

        return response()->json([
            'message' => "Unit {$unit->dt_number} berhasil ditandai RFU (Ready For Use).",
            'unit' => $unit->load('location'),
        ]);
    }

    /**
     * Admin: Delete Unit
     */
    public function deleteUnit(int $id): JsonResponse
    {
        $unit = RfuUnitRecord::findOrFail($id);
        $locationId = $unit->location_id;
        $unit->delete();

        $location = RfuLocation::find($locationId);
        if ($location) {
            $location->touch();
        }
        $this->syncLocationCount($locationId);

        return response()->json([
            'message' => 'Unit berhasil dihapus.',
        ]);
    }

    /**
     * Update Target / Counter Lokasi secara Batch (Semua Lokasi sekaligus)
     */
    public function updateLocationsBatch(Request $request): JsonResponse
    {
        $this->ensureDefaultLocationsExist();

        $locationsData = $request->input('locations');
        if (!is_array($locationsData)) {
            $locationsData = $request->all();
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($locationsData) {
            foreach ($locationsData as $key => $item) {
                if (!is_array($item)) continue;

                $locId = $item['id'] ?? (is_numeric($key) ? $key : null);
                $locCode = $item['code'] ?? (!is_numeric($key) ? $key : null);

                $location = null;
                if ($locId && is_numeric($locId) && (int)$locId > 0) {
                    $location = RfuLocation::find((int)$locId);
                }
                if (!$location && $locCode) {
                    $location = RfuLocation::where('code', $locCode)->first();
                }
                if (!$location && isset($item['name'])) {
                    $location = RfuLocation::where('name', $item['name'])->first();
                }
                if (!$location && is_numeric($key) && (int)$key >= 1 && (int)$key <= 4) {
                    $location = RfuLocation::where('sort_order', (int)$key)->first();
                }

                if ($location) {
                    $updateData = [];
                    if (array_key_exists('rfu_count', $item)) {
                        $updateData['rfu_count'] = max(0, (int)$item['rfu_count']);
                    }
                    if (array_key_exists('plan_count', $item)) {
                        $updateData['plan_count'] = max(0, (int)$item['plan_count']);
                    }
                    if (array_key_exists('on_progress_count', $item)) {
                        $updateData['on_progress_count'] = max(0, (int)$item['on_progress_count']);
                    }
                    if (!empty($updateData)) {
                        $location->update($updateData);
                        $location->touch();
                    }
                }
            }
        });

        $allLocations = RfuLocation::withCount(['activeUnits'])
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'code' => $loc->code,
                    'rfu_count' => (int)$loc->rfu_count,
                    'plan_count' => (int)$loc->plan_count,
                    'on_progress_count' => (int)($loc->active_units_count ?? $loc->on_progress_count ?? 0),
                    'active_units_count' => (int)($loc->active_units_count ?? 0),
                ];
            });

        return response()->json([
            'message' => 'Data target lokasi berhasil diperbarui.',
            'locations' => $allLocations,
        ])
        ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache');
    }

    /**
     * Admin: Update Target / Counter Lokasi (RFU & Plan)
     */
    public function updateLocation(Request $request, mixed $id): JsonResponse
    {
        $this->ensureDefaultLocationsExist();

        $location = null;
        if (is_numeric($id) && (int)$id > 0) {
            $location = RfuLocation::find((int)$id);
        }
        if (!$location) {
            $location = RfuLocation::where('code', $id)->orWhere('name', $id)->first();
        }
        if (!$location && is_numeric($id) && (int)$id >= 1 && (int)$id <= 4) {
            $location = RfuLocation::where('sort_order', (int)$id)->first();
        }

        if (!$location) {
            return response()->json([
                'message' => "Lokasi dengan ID/kode '{$id}' tidak ditemukan.",
            ], 404);
        }

        $validated = $request->validate([
            'rfu_count' => 'required|integer|min:0',
            'plan_count' => 'required|integer|min:0',
            'on_progress_count' => 'nullable|integer|min:0',
        ]);

        $location->update($validated);
        $location->touch();

        return response()->json([
            'message' => "Data counter lokasi {$location->name} berhasil diperbarui.",
            'location' => $location,
        ]);
    }

    /**
     * Helper sync on_progress_count
     */
    private function syncLocationCount(int $locationId): void
    {
        $location = RfuLocation::find($locationId);
        if ($location) {
            $count = RfuUnitRecord::where('location_id', $locationId)
                ->where('status', 'ON_PROGRESS')
                ->count();
            $location->update(['on_progress_count' => $count]);
        }
    }
}
