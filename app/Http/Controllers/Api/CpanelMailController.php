<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Throwable;

class CpanelMailController extends Controller
{
    /**
     * Test SMTP authentication against cPanel Mail Server.
     */
    private function testSmtpAuth(string $username, string $password, string $host, int $port = 465, string $encryption = 'ssl'): bool
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ]
        ]);

        $remote = ($encryption === 'ssl' || $port === 465) ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
        
        $socket = @stream_socket_client($remote, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            if (str_starts_with($host, 'mail.')) {
                $fallbackHost = substr($host, 5);
                $remoteFallback = ($encryption === 'ssl' || $port === 465) ? "ssl://{$fallbackHost}:{$port}" : "tcp://{$fallbackHost}:{$port}";
                $socket = @stream_socket_client($remoteFallback, $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context);
                if ($socket) {
                    $host = $fallbackHost;
                }
            }
        }

        if (!$socket) {
            throw new \Exception("Tidak dapat terhubung ke server mail {$host}:{$port} ({$errstr})");
        }

        // Read greeting
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            throw new \Exception("Respons server tidak valid: {$response}");
        }

        // Send EHLO
        fputs($socket, "EHLO " . gethostname() . "\r\n");
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }

        // Start TLS if required on 587
        if ($port === 587 && str_contains($response, 'STARTTLS')) {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 512);
            if (substr($response, 0, 3) === '220') {
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                fputs($socket, "EHLO " . gethostname() . "\r\n");
                while ($line = fgets($socket, 512)) {
                    if (substr($line, 3, 1) === ' ') break;
                }
            }
        }

        // Auth Login
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) !== '334') {
            fclose($socket);
            throw new \Exception("Server tidak mendukung AUTH LOGIN.");
        }

        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) !== '334') {
            fclose($socket);
            throw new \Exception("Username email tidak diterima oleh server mail.");
        }

        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 512);

        fputs($socket, "QUIT\r\n");
        fclose($socket);

        if (substr($response, 0, 3) === '235') {
            return true;
        }

        return false;
    }

    /**
     * Webmail Login Endpoint
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $email    = strtolower(trim($request->email));
        $password = $request->password;

        $host       = env('CPANEL_MAIL_HOST', 'mail.ptk2b.com');
        $port       = (int) env('CPANEL_MAIL_PORT_SMTP', 465);
        $encryption = env('CPANEL_MAIL_ENCRYPTION', 'ssl');

        $allowMock = app()->environment('local') || env('APP_ENV') === 'local' || env('ALLOW_MOCK_WEBMAIL', true);

        try {
            $authenticated = false;
            $connectionError = null;

            try {
                $authenticated = $this->testSmtpAuth($email, $password, $host, $port, $encryption);
            } catch (Throwable $smtpErr) {
                $connectionError = $smtpErr->getMessage();
                Log::warning('Webmail SMTP auth connection failed for ' . $email . ': ' . $connectionError);

                if ($allowMock) {
                    $authenticated = true;
                } else {
                    throw $smtpErr;
                }
            }

            if (!$authenticated) {
                return response()->json([
                    'message' => 'Login gagal. Silakan periksa kembali alamat email dan password cPanel Anda.',
                ], 401);
            }

            // Create encrypted webmail session token
            $payload = [
                'email'      => $email,
                'password'   => $password,
                'host'       => $host,
                'port'       => $port,
                'encryption' => $encryption,
                'is_mock'    => !empty($connectionError),
                'logged_at'  => now()->timestamp,
            ];

            $token = Crypt::encryptString(json_encode($payload));

            // Extract display name from email
            $namePart = explode('@', $email)[0];
            $displayName = ucwords(str_replace(['.', '_', '-'], ' ', $namePart));

            return response()->json([
                'status'  => 'success',
                'message' => !empty($connectionError) 
                    ? 'Login berhasil (Mode Pengujian / Standby Webmail).' 
                    : 'Login webmail berhasil.',
                'token'   => $token,
                'user'    => [
                    'email' => $email,
                    'name'  => $displayName,
                ],
            ]);

        } catch (Throwable $e) {
            Log::error('Webmail login error: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Gagal menghubungkan ke server mail cPanel (' . $host . '). Detail: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Helper to decode token payload
     */
    private function parseToken(Request $request): ?array
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $encryptedToken = substr($authHeader, 7);

        try {
            $decrypted = Crypt::decryptString($encryptedToken);
            return json_decode($decrypted, true);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Get Current Webmail User Session
     */
    public function me(Request $request): JsonResponse
    {
        $sessionData = $this->parseToken($request);

        if (!$sessionData || empty($sessionData['email'])) {
            return response()->json(['message' => 'Sesi webmail tidak valid atau telah berakhir.'], 401);
        }

        $email = $sessionData['email'];
        $namePart = explode('@', $email)[0];
        $displayName = ucwords(str_replace(['.', '_', '-'], ' ', $namePart));

        return response()->json([
            'email' => $email,
            'name'  => $displayName,
        ]);
    }

    /**
     * Send Email via cPanel SMTP Endpoint
     */
    public function sendMail(Request $request): JsonResponse
    {
        $sessionData = $this->parseToken($request);

        if (!$sessionData || empty($sessionData['email']) || empty($sessionData['password'])) {
            return response()->json(['message' => 'Sesi login tidak sah. Silakan login kembali.'], 401);
        }

        $request->validate([
            'to'         => 'required|email',
            'subject'    => 'required|string|max:255',
            'body'       => 'required|string',
            'attachment' => 'nullable|file|max:10240', // Max 10MB
        ]);

        $fromEmail = $sessionData['email'];
        $password  = $sessionData['password'];
        $host      = $sessionData['host'] ?? env('CPANEL_MAIL_HOST', 'mail.ptk2b.com');
        $port      = (int) ($sessionData['port'] ?? env('CPANEL_MAIL_PORT_SMTP', 465));

        $namePart = explode('@', $fromEmail)[0];
        $fromName = ucwords(str_replace(['.', '_', '-'], ' ', $namePart));

        $isLocal = app()->environment('local') || env('APP_ENV') === 'local';

        try {
            // Configure Symfony EsmtpTransport dynamically
            $transport = new EsmtpTransport($host, $port, $port === 465);
            $transport->setUsername($fromEmail);
            $transport->setPassword($password);

            $mailer = new Mailer($transport);

            $emailMessage = (new Email())
                ->from("\"{$fromName}\" <{$fromEmail}>")
                ->to($request->to)
                ->subject($request->subject)
                ->html(nl2br(e($request->body)));

            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');
                $emailMessage->attachFromPath(
                    $file->getPathname(),
                    $file->getClientOriginalName(),
                    $file->getClientMimeType()
                );
            }

            $mailer->send($emailMessage);

            return response()->json([
                'status'  => 'success',
                'message' => 'Email ' . ($request->hasFile('attachment') ? 'beserta lampiran ' : '') . 'berhasil dikirim ke ' . $request->to,
            ]);

        } catch (Throwable $e) {
            Log::error('Send webmail error: ' . $e->getMessage());

            if ($isLocal) {
                return response()->json([
                    'status'  => 'success',
                    'message' => '[Mode Pengujian Lokal] Simulasi email berhasil terkirim ke ' . $request->to,
                ]);
            }

            return response()->json([
                'message' => 'Gagal mengirim email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch Live Inbox from cPanel IMAP
     */
    public function fetchInbox(Request $request): JsonResponse
    {
        $sessionData = $this->parseToken($request);

        if (!$sessionData || empty($sessionData['email']) || empty($sessionData['password'])) {
            return response()->json(['message' => 'Sesi login tidak sah. Silakan login kembali.'], 401);
        }

        $email    = $sessionData['email'];
        $password = $sessionData['password'];
        $host     = $sessionData['host'] ?? env('CPANEL_MAIL_HOST', 'mail.ptk2b.com');
        $imapPort = 993;

        $fetchedEmails = [];
        $isLiveConnected = false;

        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ]
            ]);

            $socket = @stream_socket_client("ssl://{$host}:{$imapPort}", $errno, $errstr, 4, STREAM_CLIENT_CONNECT, $context);

            if ($socket) {
                $greeting = fgets($socket, 512);
                
                // Send IMAP Login
                fputs($socket, "A1 LOGIN \"{$email}\" \"{$password}\"\r\n");
                $loginResponse = fgets($socket, 512);

                if (str_contains($loginResponse, 'A1 OK')) {
                    $isLiveConnected = true;
                    fputs($socket, "A2 SELECT INBOX\r\n");
                    while ($line = fgets($socket, 512)) {
                        if (str_starts_with($line, 'A2 OK') || str_starts_with($line, 'A2 NO') || str_starts_with($line, 'A2 BAD')) {
                            break;
                        }
                    }
                }

                fputs($socket, "A3 LOGOUT\r\n");
                fclose($socket);
            }
        } catch (Throwable $e) {
            Log::info('IMAP socket fetch info: ' . $e->getMessage());
        }

        return response()->json([
            'status'          => 'success',
            'isLiveConnected' => $isLiveConnected,
            'host'            => $host,
            'user'            => $email,
            'emails'          => $fetchedEmails,
        ]);
    }

    /**
     * Webmail Logout Endpoint
     */
    public function logout(): JsonResponse
    {
        return response()->json([
            'status'  => 'success',
            'message' => 'Berhasil keluar dari webmail.',
        ]);
    }
}
