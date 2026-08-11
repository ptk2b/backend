<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Memo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MemoApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Memo::with('uploader:id,name')
            ->orderBy('created_at', 'desc');

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('memo_number', 'like', "%{$search}%");
            });
        }

        if ($request->has('category') && $request->category) {
            $query->where('category', $request->category);
        }

        $memos = $query->paginate($request->get('per_page', 15));

        return response()->json($memos);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'memo_number' => 'nullable|string|max:100',
            'category'    => 'required|string|max:100',
            'description' => 'nullable|string',
            'file'        => 'required|file|mimes:pdf|max:10240', // max 10MB
        ]);

        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('memos', $fileName, 'public');

        $memo = Memo::create([
            'title'       => $request->title,
            'memo_number' => $request->memo_number,
            'category'    => $request->category,
            'description' => $request->description,
            'file_path'   => $filePath,
            'file_name'   => $file->getClientOriginalName(),
            'file_size'   => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Memo berhasil diupload.',
            'memo'    => $memo->load('uploader:id,name'),
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $memo = Memo::findOrFail($id);

        // Delete file from storage
        if (Storage::disk('public')->exists($memo->file_path)) {
            Storage::disk('public')->delete($memo->file_path);
        }

        $memo->delete();

        return response()->json(['message' => 'Memo berhasil dihapus.']);
    }

    public function download(int $id): BinaryFileResponse
    {
        $memo = Memo::findOrFail($id);
        $path = Storage::disk('public')->path($memo->file_path);

        return response()->download($path, $memo->file_name);
    }
}
