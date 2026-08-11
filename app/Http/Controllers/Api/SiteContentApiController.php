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

        // 2. Attempt sending email copy if mailer configured
        $destinationEmail = env('CONTACT_FORM_RECIPIENT', 'info@ptk2b.com');
        $mailUsername     = env('MAIL_USERNAME');

        if (!empty($mailUsername)) {
            $body = "Pesan baru dari Form Kontak Website PT. Karya Kembar Bersama:\n\n"
                  . "• Nama: {$request->name}\n"
                  . "• Email Pengirim: {$request->email}\n"
                  . "• Perusahaan: " . ($request->company ?: '-') . "\n\n"
                  . "Pesan:\n{$request->message}\n\n"
                  . "---\nDikirim dari Form Kontak https://www.ptk2b.com pada " . now()->format('d M Y H:i:s T');

            try {
                \Illuminate\Support\Facades\Mail::raw($body, function ($mail) use ($destinationEmail, $request) {
                    $mail->to($destinationEmail)
                         ->replyTo($request->email, $request->name)
                         ->subject("Pesan Kontak Website dari {$request->name}");
                });
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Contact form email delivery error: ' . $e->getMessage());
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
}
