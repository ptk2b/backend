<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class FingerprintAttendanceController extends Controller
{
    /**
     * Get the latest fingerprint audit reconciliation dataset.
     */
    public function data(Request $request): JsonResponse
    {
        $jsonPath = base_path('../frontend/public/data/fingerprint_audit_september_2026.json');
        
        if (!File::exists($jsonPath)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dataset audit presensi belum digenerate.'
            ], 404);
        }

        $data = json_decode(File::get($jsonPath), true);

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * Sync or upload new log / schedule file.
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string|in:log,schedule',
            'file' => 'required|file|max:51200', // max 50MB
        ]);

        $file = $request->file('file');
        $type = $request->input('type');

        try {
            $filename = time() . '_' . $file->getClientOriginalName();
            $destination = storage_path('app/fingerprint/' . $type);
            
            if (!File::isDirectory($destination)) {
                File::makeDirectory($destination, 0755, true);
            }

            $file->move($destination, $filename);

            return response()->json([
                'status' => 'success',
                'message' => 'File ' . $file->getClientOriginalName() . ' berhasil diunggah dan disimpan.',
                'path' => $filename
            ]);
        } catch (\Exception $e) {
            Log::error('Fingerprint sync error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengunggah file: ' . $e->getMessage()
            ], 500);
        }
    }
}
