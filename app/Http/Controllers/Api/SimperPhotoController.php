<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SimperPhotoController extends Controller
{
    /**
     * Upload foto untuk pengajuan simper/mine permit (public endpoint, tanpa autentikasi).
     * File disimpan di storage/app/public/simper-photos/ dan dapat diakses publik.
     *
     * POST /api/public/upload-simper-photo
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg',
                'max:3072', // 3MB dalam kilobyte
            ],
        ], [
            'file.required'  => 'File foto wajib disertakan.',
            'file.image'     => 'File harus berupa gambar.',
            'file.mimes'     => 'Format file harus JPG/JPEG.',
            'file.max'       => 'Ukuran file maksimal 3 MB.',
        ]);

        $file = $request->file('file');

        // Buat nama file unik: simper_YYYYMMDD_<random>.jpg
        $filename = 'simper_' . now()->format('Ymd_His') . '_' . Str::random(8) . '.jpg';

        // Simpan ke storage/app/public/simper-photos/
        $path = $file->storeAs('simper-photos', $filename, 'public');

        if (!$path) {
            return response()->json([
                'message' => 'Gagal menyimpan file. Coba lagi.',
            ], 500);
        }

        // Buat URL publik yang bisa diakses
        $url = url('storage/' . $path);

        return response()->json([
            'success' => true,
            'url'     => $url,
            'path'    => $path,
            'filename' => $filename,
        ], 201);
    }
}
