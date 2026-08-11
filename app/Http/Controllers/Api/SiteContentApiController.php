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
}
