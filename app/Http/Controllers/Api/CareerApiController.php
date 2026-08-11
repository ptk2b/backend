<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\CareerApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CareerApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Career::query();

        // Public: only show active careers
        if (! $request->user()) {
            $query->active();
        }

        $careers = $query->orderBy('is_urgent', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($careers);
    }

    public function show(int $id): JsonResponse
    {
        $career = Career::active()->find($id);

        if (! $career) {
            return response()->json(['message' => 'Lowongan tidak ditemukan'], 404);
        }

        return response()->json($career);
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

        $career = Career::create($request->only([
            'title', 'department', 'location', 'type',
            'description', 'requirements', 'closed_date', 'is_urgent',
        ]));

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

        $career->update($request->only([
            'title', 'department', 'location', 'type',
            'description', 'requirements', 'closed_date',
            'is_urgent', 'is_active',
        ]));

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
            $career = Career::find($request->career_id);
            if ($career) {
                $careerTitle = $career->title;
                $careerDept = $career->department;
                $careerLocation = $career->location;
            }
        }

        // Handle CV file upload
        $cvPath = null;
        $originalFilename = null;

        if ($request->hasFile('cv_file')) {
            $file = $request->file('cv_file');
            $originalFilename = $file->getClientOriginalName();
            $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $originalFilename);

            $uploadDir = public_path('uploads/cvs');
            if (! file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $file->move($uploadDir, $filename);
            $cvPath = 'uploads/cvs/' . $filename;
        }

        // Store application in database (safe try-catch)
        $application = null;
        try {
            $application = CareerApplication::create([
                'career_id'    => is_numeric($request->career_id) ? (int)$request->career_id : null,
                'career_title' => $careerTitle,
                'name'         => $request->name,
                'email'        => $request->email,
                'phone'        => $request->phone,
                'cover_letter' => $request->cover_letter,
                'cv_path'      => $cvPath,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Career application DB store warning: ' . $e->getMessage());
        }

        // Send email notification to info@ptk2b.com
        $destinationEmail = env('CONTACT_FORM_RECIPIENT', 'info@ptk2b.com');
        $mailHost         = env('MAIL_HOST', 'mail.ptk2b.com');
        $mailPort         = (int) env('MAIL_PORT', 465);
        $mailUsername      = env('MAIL_USERNAME', 'info@ptk2b.com');
        $mailPassword      = env('MAIL_PASSWORD');

        $body = "Lamaran Kerja Baru — PT. Karya Kembar Bersama\n\n"
              . "Posisi: {$careerTitle}\n"
              . "Departemen: {$careerDept}\n"
              . "Lokasi: {$careerLocation}\n\n"
              . "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n"
              . "Data Pelamar:\n"
              . "• Nama: {$request->name}\n"
              . "• Email: {$request->email}\n"
              . "• Telepon: " . ($request->phone ?: '-') . "\n"
              . "• Lampiran CV: " . ($originalFilename ? "Ada ({$originalFilename})" : "Tidak ada") . "\n\n"
              . "Surat Lamaran:\n" . ($request->cover_letter ?: '-') . "\n\n"
              . "---\nDikirim dari Form Karir https://www.ptk2b.com/karir pada " . now()->format('d M Y H:i:s T');

        $fullCvPath = $cvPath ? public_path($cvPath) : null;

        if (!empty($mailPassword)) {
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
                    ->html(nl2br(e($body)));

                if ($fullCvPath && file_exists($fullCvPath)) {
                    $emailMessage->attachFromPath($fullCvPath, $originalFilename, 'application/pdf');
                }

                $mailer->send($emailMessage);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Career apply EsmtpTransport delivery error: ' . $e->getMessage());

                try {
                    \Illuminate\Support\Facades\Mail::raw($body, function ($mail) use ($destinationEmail, $request, $careerTitle, $mailUsername, $fullCvPath, $originalFilename) {
                        $mail->from($mailUsername, 'PT. Karya Kembar Bersama')
                             ->to($destinationEmail)
                             ->replyTo($request->email, $request->name)
                             ->subject("Lamaran Kerja: {$careerTitle} — dari {$request->name}");

                        if ($fullCvPath && file_exists($fullCvPath)) {
                            $mail->attach($fullCvPath, ['as' => $originalFilename, 'mime' => 'application/pdf']);
                        }
                    });
                } catch (\Throwable $e2) {
                    \Illuminate\Support\Facades\Log::warning('Career apply Mail facade error: ' . $e2->getMessage());

                    try {
                        $headers = "From: PT. Karya Kembar Bersama <{$mailUsername}>\r\n" .
                                   "Reply-To: {$request->name} <{$request->email}>\r\n" .
                                   "X-Mailer: PHP/" . phpversion() . "\r\n" .
                                   "Content-Type: text/plain; charset=UTF-8\r\n";
                        @mail($destinationEmail, "Lamaran Kerja: {$careerTitle} — dari {$request->name}", $body, $headers);
                    } catch (\Throwable $e3) {
                        \Illuminate\Support\Facades\Log::warning('Career apply native mail error: ' . $e3->getMessage());
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
}
