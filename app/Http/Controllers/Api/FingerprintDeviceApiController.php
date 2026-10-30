<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FingerprintDevice;
use App\Models\FingerprintDeviceMapping;
use App\Models\FingerprintDeviceLog;
use App\Models\FingerprintDeviceRekap;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FingerprintDeviceApiController extends Controller
{
    /**
     * List all registered devices / areas.
     */
    public function devices(Request $request): JsonResponse
    {
        $devices = FingerprintDevice::with(['assignedUser:id,name,username'])
            ->withCount(['mappings', 'logs'])
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $devices,
        ]);
    }

    /**
     * Get mappings for a specific device.
     */
    public function getMappings(Request $request, int $deviceId): JsonResponse
    {
        $mappings = FingerprintDeviceMapping::where('device_id', $deviceId)->get();

        return response()->json([
            'status' => 'success',
            'device_id' => $deviceId,
            'data' => $mappings,
        ]);
    }

    /**
     * Batch save / update mappings for a specific device.
     * Guaranteed no conflict because each device has its own isolated mappings!
     */
    public function saveMappings(Request $request, int $deviceId): JsonResponse
    {
        $request->validate([
            'mappings' => 'required|array',
            'mappings.*.finger_id' => 'required|numeric',
            'mappings.*.nik' => 'required|string',
            'mappings.*.nama' => 'nullable|string',
            'mappings.*.jabatan' => 'nullable|string',
        ]);

        $device = FingerprintDevice::findOrFail($deviceId);
        $mappings = $request->input('mappings');

        DB::beginTransaction();
        try {
            foreach ($mappings as $map) {
                FingerprintDeviceMapping::updateOrCreate(
                    [
                        'device_id' => $device->id,
                        'finger_id' => $map['finger_id'],
                    ],
                    [
                        'nik' => trim($map['nik']),
                        'nama' => $map['nama'] ?? null,
                        'jabatan' => $map['jabatan'] ?? null,
                    ]
                );
            }
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => count($mappings) . ' data mapping untuk ' . $device->name . ' berhasil disimpan.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving fingerprint mappings: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan mapping: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Batch upload scan logs (.DAT parsed taps) for a specific device.
     * Avoids duplicate entry via UNIQUE(device_id, finger_id, scan_time).
     */
    public function syncLogs(Request $request, int $deviceId): JsonResponse
    {
        $request->validate([
            'logs' => 'required|array', // Array of { finger_id: 10, scan_time: "2026-10-01 06:12:00" }
            'batch_file' => 'nullable|string',
        ]);

        $device = FingerprintDevice::findOrFail($deviceId);
        $logs = $request->input('logs');
        $batchFile = $request->input('batch_file', 'manual_sync');
        $userId = $request->user()?->id;

        $inserted = 0;
        $chunks = array_chunk($logs, 500);

        DB::beginTransaction();
        try {
            foreach ($chunks as $chunk) {
                $payload = [];
                $now = now();
                foreach ($chunk as $item) {
                    $payload[] = [
                        'device_id' => $device->id,
                        'finger_id' => $item['finger_id'],
                        'scan_time' => $item['scan_time'],
                        'verify_type' => $item['verify_type'] ?? 1,
                        'work_state' => $item['work_state'] ?? 0,
                        'uploaded_by' => $userId,
                        'batch_file' => $batchFile,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                // Insert ignore to skip duplicates safely
                $inserted += DB::table('fingerprint_device_logs')->insertOrIgnore($payload);
            }
            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => $inserted . ' log scan baru berhasil disimpan untuk ' . $device->name . '.',
                'total_inserted' => $inserted,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error syncing fingerprint logs: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan log scan: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get logs for a device within date range.
     */
    public function getLogs(Request $request, int $deviceId): JsonResponse
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = FingerprintDeviceLog::where('device_id', $deviceId);

        if ($startDate) {
            $query->where('scan_time', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('scan_time', '<=', $endDate . ' 23:59:59');
        }

        $logs = $query->orderBy('scan_time', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'device_id' => $deviceId,
            'count' => $logs->count(),
            'data' => $logs,
        ]);
    }
}

