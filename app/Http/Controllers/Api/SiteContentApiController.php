<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteContentApiController extends Controller
{
    /**
     * Get content — optionally filtered by section.
     * GET /api/content         → all sections
     * GET /api/content/hero    → only hero section
     */
    public function show(Request $request, ?string $section = null): JsonResponse
    {
        $query = SiteContent::query();

        if ($section) {
            $query->where('section', $section);
        }

        $contents = $query->get();

        // Group by section → lang → key=value
        $result = [];
        foreach ($contents as $item) {
            $value = $item->content_type === 'json'
                ? json_decode($item->content_value, true)
                : $item->content_value;

            $result[$item->section][$item->lang][$item->content_key] = $value;
        }

        return response()->json($section && isset($result[$section]) ? $result[$section] : $result);
    }

    /**
     * Update content for a section.
     * PUT /api/content/{section}
     * Body: { "lang": "id", "data": { "eyebrow": "...", "desc": "...", ... } }
     */
    public function update(Request $request, string $section): JsonResponse
    {
        $request->validate([
            'lang' => 'required|string|in:id,en',
            'data' => 'required|array',
        ]);

        $lang = $request->lang;
        $data = $request->data;

        foreach ($data as $key => $value) {
            $contentType = is_array($value) ? 'json' : 'text';
            $contentValue = is_array($value) ? json_encode($value) : $value;

            SiteContent::updateOrCreate(
                [
                    'section'     => $section,
                    'content_key' => $key,
                    'lang'        => $lang,
                ],
                [
                    'content_value' => $contentValue,
                    'content_type'  => $contentType,
                ]
            );
        }

        return response()->json(['message' => "Konten section '{$section}' berhasil diperbarui."]);
    }

    /**
     * Send contact message from website form.
     * POST /api/contact-message
     */
    public function sendContactMessage(Request $request): JsonResponse
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'company' => 'nullable|string|max:255',
            'message' => 'required|string',
        ]);

        // 1. Always store message in Database
        $msg = \App\Models\ContactMessage::create([
            'name'    => $request->name,
            'email'   => $request->email,
            'company' => $request->company,
            'message' => $request->message,
        ]);

        // 2. Send email copy to info@ptk2b.com
        $destinationEmail = env('CONTACT_FORM_RECIPIENT', 'info@ptk2b.com');
        $mailHost         = env('MAIL_HOST', 'mail.ptk2b.com');
        $mailPort         = (int) env('MAIL_PORT', 465);
        $mailUsername     = env('MAIL_USERNAME', 'info@ptk2b.com');
        $mailPassword     = env('MAIL_PASSWORD');

        $body = "Pesan baru dari Form Kontak Website PT. Karya Kembar Bersama:\n\n"
              . "• Nama: {$request->name}\n"
              . "• Email Pengirim: {$request->email}\n"
              . "• Perusahaan: " . ($request->company ?: '-') . "\n\n"
              . "Pesan:\n{$request->message}\n\n"
              . "---\nDikirim dari Form Kontak https://www.ptk2b.com pada " . now()->format('d M Y H:i:s T');

        if (!empty($mailPassword)) {
            try {
                // Direct EsmtpTransport to cPanel mail server port 465 SSL
                $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport($mailHost, $mailPort, true);
                $transport->setUsername($mailUsername);
                $transport->setPassword($mailPassword);

                $mailer = new \Symfony\Component\Mailer\Mailer($transport);
                $emailMessage = (new \Symfony\Component\Mime\Email())
                    ->from("\"PT. Karya Kembar Bersama\" <{$mailUsername}>")
                    ->to($destinationEmail)
                    ->replyTo("\"{$request->name}\" <{$request->email}>")
                    ->subject("Pesan Kontak Website dari {$request->name}")
                    ->html(nl2br(e($body)));

                $mailer->send($emailMessage);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Contact form EsmtpTransport delivery error: ' . $e->getMessage());

                // Fallback 1 to Laravel Mail facade
                try {
                    \Illuminate\Support\Facades\Mail::raw($body, function ($mail) use ($destinationEmail, $request, $mailUsername) {
                        $mail->from($mailUsername, 'PT. Karya Kembar Bersama')
                             ->to($destinationEmail)
                             ->replyTo($request->email, $request->name)
                             ->subject("Pesan Kontak Website dari {$request->name}");
                    });
                } catch (\Throwable $e2) {
                    \Illuminate\Support\Facades\Log::warning('Contact form Mail facade error: ' . $e2->getMessage());

                    // Fallback 2 to native PHP mail() for local cPanel delivery
                    try {
                        $headers = "From: PT. Karya Kembar Bersama <{$mailUsername}>\r\n" .
                                   "Reply-To: {$request->name} <{$request->email}>\r\n" .
                                   "X-Mailer: PHP/" . phpversion() . "\r\n" .
                                   "Content-Type: text/plain; charset=UTF-8\r\n";
                        @mail($destinationEmail, "Pesan Kontak Website dari {$request->name}", $body, $headers);
                    } catch (\Throwable $e3) {
                        \Illuminate\Support\Facades\Log::warning('Contact form native mail error: ' . $e3->getMessage());
                    }
                }
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Pesan Anda telah berhasil dikirim dan tersimpan.",
            'data'    => $msg,
        ]);
    }

    /**
     * Admin: Get all contact messages.
     * GET /api/admin/messages
     */
    public function getMessages(): JsonResponse
    {
        $messages = \App\Models\ContactMessage::orderBy('created_at', 'desc')->get();
        return response()->json($messages);
    }

    /**
     * Admin: Delete a contact message.
     * DELETE /api/admin/messages/{id}
     */
    public function destroyMessage(int $id): JsonResponse
    {
        $message = \App\Models\ContactMessage::findOrFail($id);
        $message->delete();
        return response()->json(['message' => 'Pesan berhasil dihapus.']);
    }

    /**
     * Admin: Upload image for CMS content.
     * POST /api/admin/content/upload-image
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp|dimensions:width=1024,height=1024|max:4096',
        ], [
            'image.required' => 'File gambar wajib diunggah.',
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Format gambar harus jpeg, png, jpg, atau webp.',
            'image.dimensions' => 'Ukuran resolusi gambar harus tepat 1024x1024 piksel.',
            'image.max' => 'Ukuran file gambar maksimal adalah 4MB.',
        ]);

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());

            // Store file using public disk (storage/app/public/uploads/cms)
            $path = $file->storeAs('uploads/cms', $filename, 'public');

            // Generate relative URL (/storage/uploads/cms/filename)
            $url = '/storage/' . $path;

            return response()->json([
                'status' => 'success',
                'url' => $url,
            ]);
        }

        return response()->json(['message' => 'Tidak ada file yang diunggah.'], 400);
    }
}

