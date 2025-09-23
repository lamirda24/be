<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function index(Request $r)

    {
        $query = File::query()->with('category');

        // is_published: default TRUE unless explicitly provided
        // if ($r->filled('is_published')) {
        //     $query->where('is_published', (int) $r->input('is_published') === 1);
        // } else {
        //     $query->where('is_published', true);
        // }

        // search: q or search
        $s = $r->query('q') ?? $r->query('search');
        if (!empty($s)) {
            $query->where(function ($qq) use ($s) {
                $qq->where('title', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            });
        }

        // category filter
        if ($r->filled('category_id')) {
            $query->where('category_id', (int) $r->input('category_id'));
        }

        // pagination: limit (per_page) with sane bounds; page is auto-handled by Laravel (?page=)
        $limit = (int) $r->query('limit', 20);
        $limit = max(1, min($limit, 100)); // clamp 1..100

        return response()->api(
            $query->orderBy('category_id', 'asc')
                ->orderByDesc('created_at')   // jika tak ada created_at, pakai ->orderByDesc('id')
                ->paginate($limit)
                ->withQueryString(),
            200
        );
    }


    public function store(Request $r)
    {
        $data = $r->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'category_id'  => 'required|exists:categories,id',
            'is_published' => 'sometimes|boolean',
            'file'         => 'required|file|max:20480',
        ]);

        $path = $r->file('file')->store('files', 'public');


        $file = new File();
        $file->title         = $data['title'];
        $file->slug          = \Illuminate\Support\Str::slug($data['title']) . '-' . \Illuminate\Support\Str::random(6);
        $file->description   = $data['description'] ?? null;
        $file->category_id   = $data['category_id'];
        $file->storage_path  = $path;
        $file->original_name = $r->file('file')->getClientOriginalName();
        $file->mime_type     = $r->file('file')->getClientMimeType();
        $file->size_bytes    = $r->file('file')->getSize();
        $file->uploaded_by   = $r->user()?->id;
        if (array_key_exists('is_published', $data)) {
            $file->is_published = $data['is_published'];
        }
        $file->save();

        return response()->api($file->load(['category']), 201);
    }

    public function update(Request $r, File $file)
    {
        $data = $r->validate([
            'title'        => 'sometimes|string|max:255',
            'description'  => 'nullable|string',
            'category_id'  => 'sometimes|exists:categories,id',
            'is_published' => 'sometimes|boolean',
            'file'         => 'nullable|file|max:20480',
        ]);

        if (isset($data['title'])) {
            $file->title = $data['title'];
            if ($r->boolean('regenerate_slug')) {
                $file->slug = Str::slug($data['title']) . '-' . Str::random(6);
            }
        }

        if (array_key_exists('description', $data)) {
            $file->description = $data['description'];
        }

        if (array_key_exists('category_id', $data)) {
            $file->category_id = $data['category_id'];
        }

        if (array_key_exists('is_published', $data)) {
            $file->is_published = $data['is_published'];
        }

        if ($r->hasFile('file')) {
            if ($file->storage_path) {
                Storage::disk('public')->delete($file->storage_path);
            }
            $path = $r->file('file')->store('files', 'public');
            $file->storage_path  = $path;
            $file->original_name = $r->file('file')->getClientOriginalName();
            $file->mime_type     = $r->file('file')->getClientMimeType();
            $file->size_bytes    = $r->file('file')->getSize();
        }

        $file->save();

        return $file->load(['category']);
    }
    public function destroy(File $file)
    {
        // Soft delete DB row; keep file in storage for potential restore
        $file->delete();

        return response()->json([
            'status'  => true,
            'message' => 'File moved to trash.',
            'data'    => ['id' => $file->id, 'slug' => $file->slug],
        ], 200);
    }
    // Restore a soft-deleted file
    public function restore($idOrSlug)
    {
        $file = File::withTrashed()
            ->where('id', $idOrSlug)->orWhere('slug', $idOrSlug)
            ->firstOrFail();

        $file->restore();

        return response()->json([
            'status' => true,
            'message' => 'File restored.',
            'data' => ['id' => $file->id, 'slug' => $file->slug],
        ], 200);
    }

    // Permanently delete (DB row + physical file)
    public function forceDestroy($idOrSlug)
    {
        $file = File::withTrashed()
            ->where('id', $idOrSlug)->orWhere('slug', $idOrSlug)
            ->firstOrFail();

        if ($file->storage_path && Storage::disk('public')->exists($file->storage_path)) {
            Storage::disk('public')->delete($file->storage_path);
        }

        $file->forceDelete();

        return response()->json([
            'status' => true,
            'message' => 'File permanently deleted.',
            'data' => ['id' => $idOrSlug],
        ], 200);
    }


    public function download($idOrSlug)
    {
        $file = \App\Models\File::where('id', $idOrSlug)
            ->orWhere('slug', $idOrSlug)
            ->firstOrFail();

        $disk = Storage::disk('public');
        $path = ltrim($file->storage_path, '/'); // contoh: files/xxx.xlsx

        if (!$disk->exists($path)) {
            abort(404, 'File not found');
        }

        // Header aman (fallback kalau mime kosong)
        $headers = [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            // kalau mau inline buka di browser, ganti `attachment` -> `inline`
            'Content-Disposition' => 'attachment; filename="' . ($file->original_name ?: basename($path)) . '"',
        ];

        // catat download
        $file->recordDownload(request()->ip(), request()->header('User-Agent'));

        // Stream via Flysystem (tidak load ke memory penuh)
        return $disk->download($path, $file->original_name ?: basename($path), $headers);
    }


    public function stats()
    {
        return response()->api([
            'total_files'     => File::count(),
            'visible_files'   => File::where('is_published', true)->count(),
            'total_downloads' => (int) File::sum('download_count'),
            'categories'      => Category::count(),
        ], 200);
    }
}
