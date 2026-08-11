<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Career;
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

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'department' => 'required|string|max:100',
            'location'   => 'required|string|max:255',
            'type'       => 'required|string|max:50',
            'is_urgent'  => 'boolean',
        ]);

        $career = Career::create($request->only([
            'title', 'department', 'location', 'type', 'is_urgent',
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
            'title'      => 'sometimes|string|max:255',
            'department' => 'sometimes|string|max:100',
            'location'   => 'sometimes|string|max:255',
            'type'       => 'sometimes|string|max:50',
            'is_urgent'  => 'sometimes|boolean',
            'is_active'  => 'sometimes|boolean',
        ]);

        $career->update($request->only([
            'title', 'department', 'location', 'type', 'is_urgent', 'is_active',
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
}
