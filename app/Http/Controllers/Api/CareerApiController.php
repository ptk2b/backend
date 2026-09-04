<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\CareerApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class CareerApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Career::query();

            // Public: only show active careers
            if (! $request->user()) {
                $query->active();
            }

            $careers = $query->orderBy('is_urgent', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($careers);
        } catch (\Throwable $e) {
            Log::error('Career index error: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $career = Career::active()->find($id);

            if (! $career) {
                return response()->json(['message' => 'Lowongan tidak ditemukan'], 404);
            }

            return response()->json($career);
        } catch (\Throwable $e) {
            Log::error('Career show error: ' . $e->getMessage());
            return response()->json(['message' => 'Lowongan tidak ditemukan'], 404);
        }
    }

    /**
     * Download or view CV PDF file.
     * GET /api/careers/cv/{filename}
     */
    public function downloadCv(Request $request, string $filename)
    {
        $rawFilename = basename($filename);
        $decodedFilename = urldecode($rawFilename);
        $rawDecodedFilename = rawurldecode($rawFilename);

        $nameVariants = array_unique([$rawFilename, $decodedFilename, $rawDecodedFilename]);

        $searchDirs = [
            public_path('uploads/cvs'),
            public_path('storage/uploads/cvs'),
            base_path('public/uploads/cvs'),
            base_path('public/storage/uploads/cvs'),
            storage_path('app/public/uploads/cvs'),
            storage_path('app/uploads/cvs'),
            base_path('../public_html/uploads/cvs'),
            base_path('../public_html/storage/uploads/cvs'),
            base_path('../../public_html/uploads/cvs'),
            base_path('../../public_html/storage/uploads/cvs'),
            base_path('uploads/cvs'),
            base_path('../uploads/cvs'),
        ];

        // 1. Direct candidate matching
        foreach ($searchDirs as $dir) {
            if (!$dir || !file_exists($dir)) continue;

            foreach ($nameVariants as $variant) {
                $filePath = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $variant;
                if (file_exists($filePath) && is_file($filePath)) {
                    return $this->serveCvFile($request, $filePath, $variant);
                }
            }
        }

        // 2. Fuzzy matching (e.g. filename with special characters or prefix timestamp)
        foreach ($searchDirs as $dir) {
            if (!$dir || !file_exists($dir)) continue;

            foreach ($nameVariants as $variant) {
                $matches = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*' . $variant . '*');
                if (!empty($matches)) {
                    foreach ($matches as $match) {
                        if (file_exists($match) && is_file($match)) {
                            return $this->serveCvFile($request, $match, basename($match));
                        }
                    }
                }
            }
        }

        return response()->json([
            'status'  => 'error',
            'message' => 'File CV tidak ditemukan di server atau berkas telah dipindahkan.',
        ], 404);
    }

    private function serveCvFile(Request $request, string $path, string $filename)
    {
        if ($request->boolean('download')) {
            return response()->download($path, $filename, [
                'Content-Type'                => 'application/pdf',
                'Access-Control-Allow-Origin' => '*',
            ]);
        }

        return response()->file($path, [
            'Content-Type'                => 'application/pdf',
            'Content-Disposition'         => 'inline; filename="' . $filename . '"',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control'               => 'public, max-age=86400',
            'X-Content-Type-Options'      => 'nosniff',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'        => 'required|string|max:255',
            'department'   => 'required|string|max:100',
            'location'     => 'required|string|max:255',
            'type'         => 'required|string|max:50',
            'description'  => 'nullable|string',
            'requirements' => 'nullable|string',
            'closed_date'  => 'nullable|date',
            'is_urgent'    => 'boolean',
        ]);

        $payload = $request->only([
            'title', 'department', 'location', 'type',
            'description', 'requirements', 'closed_date', 'is_urgent',
        ]);

        if (array_key_exists('closed_date', $payload) && empty($payload['closed_date'])) {
            $payload['closed_date'] = null;
        }

        // Dynamically filter payload to columns that exist in the database table
        try {
            if (Schema::hasTable('careers')) {
                $columns = Schema::getColumnListing('careers');
                $payload = array_intersect_key($payload, array_flip($columns));
            }
        } catch (\Throwable $e) {
            Log::warning('Schema listing warning: ' . $e->getMessage());
        }

        $career = Career::create($payload);

        return response()->json([
            'message' => 'Lowongan berhasil ditambahkan.',
            'career'  => $career,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $career = Career::findOrFail($id);

        $request->validate([
            'title'        => 'sometimes|string|max:255',
            'department'   => 'sometimes|string|max:100',
            'location'     => 'sometimes|string|max:255',
            'type'         => 'sometimes|string|max:50',
            'description'  => 'sometimes|nullable|string',
            'requirements' => 'sometimes|nullable|string',
            'closed_date'  => 'sometimes|nullable|date',
            'is_urgent'    => 'sometimes|boolean',
            'is_active'    => 'sometimes|boolean',
        ]);

        $payload = $request->only([
            'title', 'department', 'location', 'type',
            'description', 'requirements', 'closed_date',
            'is_urgent', 'is_active',
        ]);

        if (array_key_exists('closed_date', $payload) && empty($payload['closed_date'])) {
            $payload['closed_date'] = null;
        }

        // Dynamically filter payload to columns that exist in the database table
        try {
            if (Schema::hasTable('careers')) {
                $columns = Schema::getColumnListing('careers');
                $payload = array_intersect_key($payload, array_flip($columns));
            }
        } catch (\Throwable $e) {
            Log::warning('Schema listing warning: ' . $e->getMessage());
        }

        $career->update($payload);

        return response()->json([
            'message' => 'Lowongan berhasil diperbarui.',
            'career'  => $career,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $career = Career::findOrFail($id);
        $career->delete();

        return response()->json(['message' => 'Lowongan berhasil dihapus.']);
    }

    /**
     * Submit a career application.
     * POST /api/career-apply
     */
    public function apply(Request $request): JsonResponse
    {
        $request->validate([
            'career_id'    => 'nullable',
            'career_title' => 'nullable|string|max:255',
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|max:255',
            'phone'        => 'nullable|string|max:30',
            'cover_letter' => 'nullable|string',
            'cv_file'      => 'nullable|file|mimes:pdf|max:3072', // PDF max 3MB (3072 KB)
        ]);

        // Find career or set fallback title
        $careerTitle = $request->career_title ?? 'Lowongan Umum';
        $careerDept = '-';
        $careerLocation = '-';

        if ($request->filled('career_id')) {
            try {
                $career = Career::find($request->career_id);
                if ($career) {
                    $careerTitle = $career->title;
                    $careerDept = $career->department;
                    $careerLocation = $career->location;
                }
            } catch (\Throwable $e) {
                Log::warning('Career find error in apply: ' . $e->getMessage());
            }
        }

        // Handle CV file upload
        $cvPath = null;
        $savedFilename = null;
        $originalFilename = null;
        $actualCvFile = null;

        if ($request->hasFile('cv_file')) {
            try {
                $file = $request->file('cv_file');
                $originalFilename = $file->getClientOriginalName();
                $savedFilename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalFilename);

                // Target directories for saving and cross-environment accessibility
                $dirs = [
                    public_path('uploads/cvs'),
                    public_path('storage/uploads/cvs'),
                    base_path('../public_html/uploads/cvs'),
                    base_path('../public_html/storage/uploads/cvs'),
                    storage_path('app/public/uploads/cvs'),
                    storage_path('app/uploads/cvs'),
                ];

                foreach ($dirs as $dir) {
                    if (! file_exists($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                }

                $movedFile = $file->move($dirs[0], $savedFilename);
                if ($movedFile) {
                    $actualCvFile = $movedFile->getRealPath() ?: ($dirs[0] . DIRECTORY_SEPARATOR . $savedFilename);
                } else {
                    $actualCvFile = $dirs[0] . DIRECTORY_SEPARATOR . $savedFilename;
                }

                // Copy to other locations so it's accessible across all server configurations (cPanel/direct static/storage)
                if (file_exists($actualCvFile)) {
                    foreach (array_slice($dirs, 1) as $fallbackDir) {
                        if (file_exists($fallbackDir)) {
                            @copy($actualCvFile, $fallbackDir . DIRECTORY_SEPARATOR . $savedFilename);
                        }
                    }
                }

                $cvPath = 'uploads/cvs/' . $savedFilename;
            } catch (\Throwable $e) {
                Log::error('CV file upload error: ' . $e->getMessage());
            }
        }

        // Find actual file path on disk if not already set
        if ($savedFilename && (! $actualCvFile || ! file_exists($actualCvFile))) {
            $candidates = [
                public_path('uploads/cvs/' . $savedFilename),
                base_path('public/uploads/cvs/' . $savedFilename),
                base_path('../public_html/uploads/cvs/' . $savedFilename),
                base_path('../../public_html/uploads/cvs/' . $savedFilename),
                storage_path('app/public/uploads/cvs/' . $savedFilename),
                base_path('uploads/cvs/' . $savedFilename),
            ];
            foreach ($candidates as $candidate) {
                if ($candidate && file_exists($candidate)) {
                    $actualCvFile = $candidate;
                    break;
                }
            }
        }

        // Read expanded fields
        $tempatLahir        = $request->tempat_lahir ?? '';
        $tanggalLahir       = $request->tanggal_lahir ?? '';
        $alamatDomisili     = $request->alamat_domisili ?? '';
        $pendidikanTerakhir = $request->pendidikan_terakhir ?? '';
        $namaLembaga        = $request->nama_lembaga ?? '';
        $sertifikasi        = $request->sertifikasi ?? '';
        $pengalamanTerakhir = $request->pengalaman_terakhir ?? '';
        $jabatanTerakhir    = $request->jabatan_terakhir ?? '';
        $masaKerja          = $request->masa_kerja ?? '';
        $rekomendasi        = $request->rekomendasi ?? '';

        // Calculate age if not directly provided
        $calculatedUmur = $request->umur ?? '';
        if (empty($calculatedUmur) && !empty($tanggalLahir)) {
            try {
                $calculatedUmur = (string) \Carbon\Carbon::parse($tanggalLahir)->age;
            } catch (\Throwable $e) {
                // Ignore parse errors
            }
        }

        // Generate absolute API download URL for Google Sheets and Email (/api/careers/cv/{filename})
        $cvDownloadUrl = null;
        if ($savedFilename) {
            $configuredAppUrl = env('APP_URL');
            if (!empty($configuredAppUrl) && str_contains($configuredAppUrl, 'ptk2b.com')) {
                $cvDownloadUrl = 'https://api.ptk2b.com/api/careers/cv/' . $savedFilename;
            } elseif (!empty($configuredAppUrl) && !str_contains($configuredAppUrl, 'localhost') && !str_contains($configuredAppUrl, '127.0.0.1')) {
                $cvDownloadUrl = rtrim($configuredAppUrl, '/') . '/api/careers/cv/' . $savedFilename;
            } else {
                $host = $request->getSchemeAndHttpHost();
                if (!empty($host) && str_contains($host, 'ptk2b.com')) {
                    $cvDownloadUrl = 'https://api.ptk2b.com/api/careers/cv/' . $savedFilename;
                } elseif (!empty($host) && !str_contains($host, 'localhost') && !str_contains($host, '127.0.0.1')) {
                    $host = preg_replace('/^http:/i', 'https:', $host);
                    $cvDownloadUrl = rtrim($host, '/') . '/api/careers/cv/' . $savedFilename;
                } else {
                    $cvDownloadUrl = url('/api/careers/cv/' . $savedFilename);
                }
            }
        }

        // Send to Google Sheets Webhook if configured
        $webhookUrl = env('GOOGLE_SHEETS_WEBHOOK_URL', 'https://script.google.com/macros/s/AKfycbyxm5dFSoXqMYNYCfFNHRsitwHbRztFcqDmkgPXY7qCA4Ut5lKxVgiwqu-uRk7NIS5H/exec');
        if (!empty($webhookUrl)) {
            try {
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                    ->timeout(30)
                    ->asJson()
                    ->post($webhookUrl, [
                        'tanggal_masuk'       => now()->format('d/m/Y H:i'),
                        'nama'                => (string) ($request->name ?? ''),
                        'email'               => (string) ($request->email ?? ''),
                        'umur'                => (string) ($calculatedUmur ?? ''),
                        'tempat_lahir'        => (string) ($tempatLahir ?? ''),
                        'tanggal_lahir'       => (string) ($tanggalLahir ?? ''),
                        'nomor_hp'            => (string) ($request->phone ?? ''),
                        'phone'               => (string) ($request->phone ?? ''),
                        'alamat_domisili'     => (string) ($alamatDomisili ?? ''),
                        'pendidikan_terakhir' => (string) ($pendidikanTerakhir ?? ''),
                        'nama_lembaga'        => (string) ($namaLembaga ?? ''),
                        'sertifikasi'         => (string) ($sertifikasi ?? ''),
                        'pengalaman_terakhir' => (string) ($pengalamanTerakhir ?? ''),
                        'jabatan_terakhir'    => (string) ($jabatanTerakhir ?? ''),
                        'masa_kerja'          => (string) ($masaKerja ?? ''),
                        'jabatan_dilamar'     => (string) ($careerTitle ?? 'Lowongan Umum'),
                        'rekomendasi'         => (string) ($rekomendasi ?? ''),
                        'surat_lamaran'       => (string) ($request->cover_letter ?? ''),
                        'cover_letter'        => (string) ($request->cover_letter ?? ''),
                        'cv_url'              => (string) ($cvDownloadUrl ?? ''),
                        'cv_nama_file'        => (string) ($originalFilename ?? ''),
                    ]);

                if (!$response->successful()) {
                    Log::warning('Google Sheets Webhook returned status ' . $response->status() . ': ' . substr($response->body(), 0, 200));
                }
            } catch (\Throwable $e) {
                Log::warning('Google Sheets Webhook error: ' . $e->getMessage());
            }
        }

        // Store application in database (safe try-catch with dual fallback)
        $application = null;
        try {
            $applicationData = [
                'career_id'           => is_numeric($request->career_id) ? (int)$request->career_id : null,
                'career_title'        => $careerTitle,
                'name'                => $request->name,
                'email'               => $request->email,
                'phone'               => $request->phone,
                'tempat_lahir'        => $tempatLahir,
                'tanggal_lahir'       => $tanggalLahir,
                'alamat_domisili'     => $alamatDomisili,
                'pendidikan_terakhir' => $pendidikanTerakhir,
                'nama_lembaga'        => $namaLembaga,
                'sertifikasi'         => $sertifikasi,
                'pengalaman_terakhir' => $pengalamanTerakhir,
                'jabatan_terakhir'    => $jabatanTerakhir,
                'masa_kerja'          => $masaKerja,
                'rekomendasi'         => $rekomendasi,
                'cover_letter'        => $request->cover_letter,
                'cv_path'             => $cvPath,
            ];

            $application = CareerApplication::create($applicationData);
        } catch (\Throwable $e) {
            Log::warning('Career application DB store expanded error: ' . $e->getMessage());

            // Fallback for older table schema (basic columns only)
            try {
                $basicData = [
                    'career_id'    => is_numeric($request->career_id) ? (int)$request->career_id : null,
                    'career_title' => $careerTitle,
                    'name'         => $request->name,
                    'email'        => $request->email,
                    'phone'        => $request->phone,
                    'cover_letter' => $request->cover_letter,
                    'cv_path'      => $cvPath,
                ];
                $application = CareerApplication::create($basicData);
            } catch (\Throwable $e2) {
                Log::error('Career application DB store fatal error: ' . $e2->getMessage());
            }
        }

        // Send email notification to info@ptk2b.com
        $destinationEmail = env('CONTACT_FORM_RECIPIENT', 'info@ptk2b.com');
        $mailHost         = env('MAIL_HOST', 'mail.ptk2b.com');
        $mailPort         = (int) env('MAIL_PORT', 465);
        $mailUsername      = env('MAIL_USERNAME', 'info@ptk2b.com');
        $mailPassword      = env('MAIL_PASSWORD');

        $cvInfoText = $originalFilename
            ? "Ada ({$originalFilename})" . ($cvDownloadUrl ? "\n  » Direct Link Unduh CV: {$cvDownloadUrl}" : "")
            : "Tidak ada";

        $cvInfoHtml = $originalFilename
            ? "Ada (<b>{$originalFilename}</b>)" . ($cvDownloadUrl ? "<br>📥 <b>Link Direct Unduh CV:</b> <a href=\"{$cvDownloadUrl}\" target=\"_blank\" style=\"color:#1B3A6B;font-weight:bold;text-decoration:underline;\">{$cvDownloadUrl}</a>" : "")
            : "Tidak ada";

        $bodyText = "Lamaran Kerja Baru — PT. Karya Kembar Bersama\n\n"
                  . "Posisi Dilamar: {$careerTitle}\n"
                  . "Departemen: {$careerDept}\n"
                  . "Lokasi: {$careerLocation}\n\n"
                  . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
                  . "Data Pelamar:\n"
                  . "• Nama: {$request->name}\n"
                  . "• Email: {$request->email}\n"
                  . "• Telepon: " . ($request->phone ?: '-') . "\n"
                  . "• TTL: " . ($tempatLahir ? "{$tempatLahir}, " : "") . ($tanggalLahir ?: '-') . "\n"
                  . "• Alamat Domisili: " . ($alamatDomisili ?: '-') . "\n"
                  . "• Pendidikan Terakhir: " . ($pendidikanTerakhir ?: '-') . " (" . ($namaLembaga ?: '-') . ")\n"
                  . "• Sertifikasi: " . ($sertifikasi ?: '-') . "\n"
                  . "• Pengalaman Terakhir: " . ($pengalamanTerakhir ?: '-') . "\n"
                  . "• Jabatan Terakhir: " . ($jabatanTerakhir ?: '-') . "\n"
                  . "• Masa Kerja: " . ($masaKerja ?: '-') . "\n"
                  . "• Rekomendasi/Referensi: " . ($rekomendasi ?: '-') . "\n"
                  . "• Lampiran CV: {$cvInfoText}\n\n"
                  . "Surat Lamaran / Catatan:\n" . ($request->cover_letter ?: '-') . "\n\n"
                  . "---\nDikirim dari Form Karir https://www.ptk2b.com/karir pada " . now()->format('d M Y H:i:s T');

        $bodyHtml = "<h3>Lamaran Kerja Baru — PT. Karya Kembar Bersama</h3>"
                  . "<p><b>Posisi Dilamar:</b> {$careerTitle}<br>"
                  . "<b>Departemen:</b> {$careerDept}<br>"
                  . "<b>Lokasi:</b> {$careerLocation}</p>"
                  . "<hr style=\"border:0;border-top:1px solid #ccc;margin:15px 0;\">"
                  . "<h4>Data Pelamar:</h4>"
                  . "<ul>"
                  . "<li><b>Nama:</b> {$request->name}</li>"
                  . "<li><b>Email:</b> {$request->email}</li>"
                  . "<li><b>Telepon:</b> " . ($request->phone ?: '-') . "</li>"
                  . "<li><b>TTL:</b> " . ($tempatLahir ? "{$tempatLahir}, " : "") . ($tanggalLahir ?: '-') . "</li>"
                  . "<li><b>Alamat Domisili:</b> " . ($alamatDomisili ?: '-') . "</li>"
                  . "<li><b>Pendidikan:</b> " . ($pendidikanTerakhir ?: '-') . " (" . ($namaLembaga ?: '-') . ")</li>"
                  . "<li><b>Sertifikasi:</b> " . ($sertifikasi ?: '-') . "</li>"
                  . "<li><b>Pengalaman Kerja Terakhir:</b> " . ($pengalamanTerakhir ?: '-') . "</li>"
                  . "<li><b>Jabatan Terakhir:</b> " . ($jabatanTerakhir ?: '-') . "</li>"
                  . "<li><b>Masa Kerja:</b> " . ($masaKerja ?: '-') . "</li>"
                  . "<li><b>Rekomendasi / Referensi:</b> " . ($rekomendasi ?: '-') . "</li>"
                  . "<li><b>Lampiran CV:</b> {$cvInfoHtml}</li>"
                  . "</ul>"
                  . "<h4>Surat Lamaran / Catatan:</h4>"
                  . "<blockquote style=\"background:#f9f9f9;padding:10px;border-left:4px solid #1B3A6B;\">" . nl2br(e($request->cover_letter ?: '-')) . "</blockquote>"
                  . "<hr style=\"border:0;border-top:1px solid #eee;\">"
                  . "<p style=\"font-size:12px;color:#777;\">Dikirim dari Form Karir <a href=\"https://www.ptk2b.com/karir\">https://www.ptk2b.com/karir</a> pada " . now()->format('d M Y H:i:s T') . "</p>";

        if (!empty($mailPassword)) {
            $emailSent = false;

            // Tier 1: Try EsmtpTransport with CV attachment
            try {
                $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport($mailHost, $mailPort, true);
                $transport->setUsername($mailUsername);
                $transport->setPassword($mailPassword);

                $mailer = new \Symfony\Component\Mailer\Mailer($transport);
                $emailMessage = (new \Symfony\Component\Mime\Email())
                    ->from("\"PT. Karya Kembar Bersama\" <{$mailUsername}>")
                    ->to($destinationEmail)
                    ->replyTo("\"{$request->name}\" <{$request->email}>")
                    ->subject("Lamaran Kerja: {$careerTitle} — dari {$request->name}")
                    ->html($bodyHtml)
                    ->text($bodyText);

                if ($actualCvFile && file_exists($actualCvFile)) {
                    $emailMessage->attachFromPath($actualCvFile, $originalFilename ?? 'CV.pdf', 'application/pdf');
                }

                $mailer->send($emailMessage);
                $emailSent = true;
            } catch (\Throwable $e) {
                Log::warning('Career apply Tier 1 (with attachment) error: ' . $e->getMessage());

                // Tier 2: Retry EsmtpTransport WITHOUT attachment (prevents SMTP timeout on large files)
                try {
                    $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport($mailHost, $mailPort, true);
                    $transport->setUsername($mailUsername);
                    $transport->setPassword($mailPassword);

                    $mailer = new \Symfony\Component\Mailer\Mailer($transport);
                    $emailMessageNoAtt = (new \Symfony\Component\Mime\Email())
                        ->from("\"PT. Karya Kembar Bersama\" <{$mailUsername}>")
                        ->to($destinationEmail)
                        ->replyTo("\"{$request->name}\" <{$request->email}>")
                        ->subject("Lamaran Kerja: {$careerTitle} — dari {$request->name}")
                        ->html($bodyHtml)
                        ->text($bodyText);

                    $mailer->send($emailMessageNoAtt);
                    $emailSent = true;
                    Log::info('Career apply Tier 2 (without attachment, direct link used) succeeded.');
                } catch (\Throwable $e2) {
                    Log::warning('Career apply Tier 2 error: ' . $e2->getMessage());
                }
            }

            // Tier 3: Laravel Mail facade fallback
            if (!$emailSent) {
                try {
                    \Illuminate\Support\Facades\Mail::html($bodyHtml, function ($mail) use ($destinationEmail, $request, $careerTitle, $mailUsername) {
                        $mail->from($mailUsername, 'PT. Karya Kembar Bersama')
                             ->to($destinationEmail)
                             ->replyTo($request->email, $request->name)
                             ->subject("Lamaran Kerja: {$careerTitle} — dari {$request->name}");
                    });
                    $emailSent = true;
                } catch (\Throwable $e3) {
                    Log::warning('Career apply Tier 3 (Mail facade) error: ' . $e3->getMessage());

                    // Tier 4: Native PHP mail() fallback
                    try {
                        $headers = "From: PT. Karya Kembar Bersama <{$mailUsername}>\r\n" .
                                   "Reply-To: {$request->name} <{$request->email}>\r\n" .
                                   "X-Mailer: PHP/" . phpversion() . "\r\n" .
                                   "Content-Type: text/html; charset=UTF-8\r\n";
                        @mail($destinationEmail, "Lamaran Kerja: {$careerTitle} — dari {$request->name}", $bodyHtml, $headers);
                    } catch (\Throwable $e4) {
                        Log::warning('Career apply Tier 4 (native mail) error: ' . $e4->getMessage());
                    }
                }
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Lamaran Anda telah berhasil dikirim.',
            'data'    => $application,
        ]);
    }

    /**
     * Admin: Get all career applications.
     * GET /api/admin/applications
     */
    public function getApplications(): JsonResponse
    {
        try {
            $applications = CareerApplication::orderBy('created_at', 'desc')->get();
            return response()->json($applications);
        } catch (\Throwable $e) {
            Log::error('Get applications error: ' . $e->getMessage());
            return response()->json([]);
        }
    }

    /**
     * Admin: Delete a career application.
     * DELETE /api/admin/applications/{id}
     */
    public function destroyApplication(int $id): JsonResponse
    {
        try {
            $application = CareerApplication::findOrFail($id);
            $application->delete();
            return response()->json(['message' => 'Lamaran berhasil dihapus.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Gagal menghapus lamaran.'], 500);
        }
    }
}
