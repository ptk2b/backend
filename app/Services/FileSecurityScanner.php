<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class FileSecurityScanner
{
    /**
     * Dangerous file extensions that should NEVER appear in any segment of the filename
     * (prevents double extensions like exploit.php.pdf, shell.phtml.png)
     */
    protected const DANGEROUS_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phar',
        'exe', 'sh', 'bash', 'cmd', 'bat', 'bin', 'cgi', 'pl', 'py', 'js',
        'vbs', 'jar', 'msi', 'com', 'scr', 'dll', 'asp', 'aspx', 'jsp', 'shtml', 'htm', 'html'
    ];

    /**
     * Scan an uploaded file for security violations.
     * Returns null if clean, or an error string if violation is found.
     *
     * @param mixed $file
     * @param array $allowedExtensions e.g. ['pdf', 'jpg', 'jpeg', 'png']
     * @param int $maxKb Max size in kilobytes (default 3072 = 3MB)
     * @return string|null
     */
    public static function scan(mixed $file, array $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'], int $maxKb = 3072): ?string
    {
        if (!$file instanceof UploadedFile) {
            return 'Berkas yang diunggah tidak valid.';
        }

        if (!$file->isValid()) {
            return 'Pengunggahan berkas gagal atau file rusak.';
        }

        // 1. Check File Size
        if ($file->getSize() > ($maxKb * 1024)) {
            $maxMb = round($maxKb / 1024, 1);
            return "Ukuran berkas melebihi batas maksimal ({$maxMb}MB).";
        }

        // 2. Check Filename for Null Bytes and Dangerous Characters
        $originalName = $file->getClientOriginalName();
        if (str_contains($originalName, "\0") || str_contains($originalName, '%00')) {
            return 'Nama berkas mengandung karakter berbahaya (null byte).';
        }

        // 3. Double Extension Detection (e.g., document.php.pdf)
        $normalizedName = strtolower($originalName);
        $nameParts = explode('.', $normalizedName);
        if (count($nameParts) > 1) {
            // Check all segments except the last one
            for ($i = 0; $i < count($nameParts) - 1; $i++) {
                if (in_array($nameParts[$i], self::DANGEROUS_EXTENSIONS, true)) {
                    return 'Berkas ditolak: Terdeteksi upaya penyusupan ekstensi terlarang (double extension).';
                }
            }
        }

        // 4. Check Final Extension against Allowed List
        $clientExt = strtolower($file->getClientOriginalExtension());
        $allowedExtsLower = array_map('strtolower', $allowedExtensions);
        if (!in_array($clientExt, $allowedExtsLower, true)) {
            return 'Format ekstensi berkas tidak diizinkan. Format yang diperbolehkan: ' . strtoupper(implode(', ', $allowedExtensions));
        }

        // 5. Verify File Existence & Readability
        $realPath = $file->getRealPath();
        if (!$realPath || !file_exists($realPath) || !is_readable($realPath)) {
            return 'Berkas sementara tidak dapat dibaca oleh sistem.';
        }

        // 6. Magic Bytes Verification (File Header Signature)
        $magicCheck = self::verifyMagicBytes($realPath, $clientExt);
        if (!$magicCheck['valid']) {
            return $magicCheck['message'];
        }

        // 7. Deep Content / Payload Inspection
        // Scan for embedded PHP tags, webshells, or executable script markers
        $payloadCheck = self::scanForSuspiciousPayload($realPath, $clientExt);
        if (!$payloadCheck['valid']) {
            return $payloadCheck['message'];
        }

        return null; // File is clean!
    }

    /**
     * Verify header signature (Magic Bytes)
     */
    protected static function verifyMagicBytes(string $path, string $ext): array
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return ['valid' => false, 'message' => 'Gagal membaca header berkas.'];
        }

        $header = fread($handle, 32);
        fclose($handle);

        if ($header === false || strlen($header) < 4) {
            return ['valid' => false, 'message' => 'Berkas kosong atau format tidak terbaca.'];
        }

        switch (strtolower($ext)) {
            case 'pdf':
                // PDF magic bytes: %PDF- (0x25, 0x50, 0x44, 0x46, 0x2D)
                if (!str_starts_with($header, '%PDF-')) {
                    return ['valid' => false, 'message' => 'Struktur berkas bukan dokumen PDF yang valid (magic bytes mismatch).'];
                }
                break;

            case 'jpg':
            case 'jpeg':
                // JPEG magic bytes: FF D8 FF
                $hex = bin2hex(substr($header, 0, 3));
                if (strtolower($hex) !== 'ffd8ff') {
                    return ['valid' => false, 'message' => 'Struktur berkas bukan gambar JPEG/JPG yang valid.'];
                }
                // Verify with getimagesize to ensure it's a decodable image
                $imageInfo = @getimagesize($path);
                if ($imageInfo === false || !in_array($imageInfo[2], [IMAGETYPE_JPEG], true)) {
                    return ['valid' => false, 'message' => 'Struktur gambar JPEG tidak valid atau rusak.'];
                }
                break;

            case 'png':
                // PNG magic bytes: 89 50 4E 47 0D 0A 1A 0A
                $hex = bin2hex(substr($header, 0, 8));
                if (strtolower($hex) !== '89504e470d0a1a0a') {
                    return ['valid' => false, 'message' => 'Struktur berkas bukan gambar PNG yang valid.'];
                }
                // Verify with getimagesize to ensure it's a decodable image
                $imageInfo = @getimagesize($path);
                if ($imageInfo === false || !in_array($imageInfo[2], [IMAGETYPE_PNG], true)) {
                    return ['valid' => false, 'message' => 'Struktur gambar PNG tidak valid atau rusak.'];
                }
                break;

            case 'webp':
                // WEBP magic bytes: RIFF....WEBP
                if (!str_starts_with($header, 'RIFF') || substr($header, 8, 4) !== 'WEBP') {
                    return ['valid' => false, 'message' => 'Struktur berkas bukan gambar WEBP yang valid.'];
                }
                break;
        }

        return ['valid' => true];
    }

    /**
     * Inspect file content for active script tags or PHP backdoor injection
     */
    protected static function scanForSuspiciousPayload(string $path, string $ext): array
    {
        $fileSize = filesize($path);
        if ($fileSize === 0) {
            return ['valid' => false, 'message' => 'Berkas tidak memiliki isi (0 bytes).'];
        }

        $readLength = min($fileSize, 2 * 1024 * 1024); // read up to 2MB
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return ['valid' => true];
        }
        $content = fread($handle, $readLength);
        fclose($handle);

        if ($content === false) {
            return ['valid' => true];
        }

        // Dangerous signatures to detect PHP backdoor / webshell injection
        $dangerousPatterns = [
            '/<\?php/i',
            '/<\?=/i',
            '/<script[\s>]/i',
            '/eval\s*\(/i',
            '/(?:passthru|shell_exec|system|proc_open|popen)\s*\(/i',
            '/__halt_compiler\s*\(/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return [
                    'valid' => false,
                    'message' => 'Peringatan Keamanan: Berkas ditolak karena mengandung kode skrip tersembunyi (malware/webshell).'
                ];
            }
        }

        return ['valid' => true];
    }
}
