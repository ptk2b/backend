<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RfuLocation;
use App\Models\RfuUnitRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RfuApiController extends Controller
{
    /**
     * Public endpoint untuk halaman Owner (tanpa login)
     */
    public function publicDashboard(Request $request): JsonResponse
    {
        $locations = RfuLocation::with(['activeUnits'])
            ->orderBy('sort_order', 'asc')
            ->get();

        $totalRfu = $locations->sum('rfu_count');
        $totalPlan = $locations->sum('plan_count');
        $totalOnProgress = $locations->sum(function ($loc) {
            return $loc->activeUnits->count();
        });

        $readinessPercentage = $totalPlan > 0
            ? round(($totalRfu / $totalPlan) * 100, 1)
            : 0;

        $now = Carbon::now('Asia/Makassar');

        return response()->json([
            'report_time' => $now->format('H:i'),
            'report_date' => $now->translatedFormat('d F Y'),
            'timestamp' => $now->toIso8601String(),
            'summary' => [
                'total_rfu' => $totalRfu,
                'total_plan' => $totalPlan,
                'total_on_progress' => $totalOnProgress,
                'readiness_percentage' => $readinessPercentage,
            ],
            'locations' => $locations->map(function ($loc) {
                return [
                    'id' => $loc->id,
                    'name' => $loc->name,
                    'code' => $loc->code,
                    'rfu_count' => $loc->rfu_count,
                    'plan_count' => $loc->plan_count,
                    'on_progress_count' => $loc->activeUnits->count(),
                    'units' => $loc->activeUnits,
                ];
            }),
        ]);
    }

    /**
     * Admin Index - get units with filters (status, location, search)
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $status = $request->query('status', 'ON_PROGRESS');
        $locationId = $request->query('location_id');
        $search = $request->query('search');

        $locations = RfuLocation::withCount(['activeUnits'])->orderBy('sort_order', 'asc')->get();

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

        $totalRfu = $locations->sum('rfu_count');
        $totalPlan = $locations->sum('plan_count');
        $totalActive = RfuUnitRecord::where('status', 'ON_PROGRESS')->count();

        return response()->json([
            'locations' => $locations,
            'units' => $units,
            'summary' => [
                'total_rfu' => $totalRfu,
                'total_plan' => $totalPlan,
                'total_on_progress' => $totalActive,
            ]
        ]);
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
        $locationsData = $request->input('locations');
        if (!is_array($locationsData)) {
            $locationsData = $request->all();
        }

        $updated = [];
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
                if (isset($item['rfu_count'])) {
                    $updateData['rfu_count'] = max(0, (int)$item['rfu_count']);
                }
                if (isset($item['plan_count'])) {
                    $updateData['plan_count'] = max(0, (int)$item['plan_count']);
                }
                if (isset($item['on_progress_count'])) {
                    $updateData['on_progress_count'] = max(0, (int)$item['on_progress_count']);
                }
                if (!empty($updateData)) {
                    $location->update($updateData);
                    $updated[] = $location->fresh();
                }
            }
        }

        return response()->json([
            'message' => 'Data target lokasi berhasil diperbarui.',
            'locations' => $updated,
        ]);
    }

    /**
     * Admin: Update Target / Counter Lokasi (RFU & Plan)
     */
    public function updateLocation(Request $request, mixed $id): JsonResponse
    {
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
