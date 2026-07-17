<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentSection;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $media = Media::query()
            ->when($request->query('type') === 'image', fn ($q) => $q->where('mime_type', 'like', 'image/%'))
            ->when($request->filled('search'), fn ($q) => $q->where('original_name', 'like', '%'.$request->string('search').'%'))
            ->latest('id')
            ->paginate(24)
            ->through($this->payload(...));

        return response()->json($media);
    }

    /** Detail payload plus the sections whose body references this file. */
    public function show(Media $media): JsonResponse
    {
        return response()->json([
            'media' => [...$this->payload($media), 'used_in' => $this->usedInLabels($media)->values()->all()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,gif,pdf', 'max:5120'],
        ]);

        $file = $request->file('file');
        $media = Media::create([
            'path' => $file->store('media', 'public'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['media' => $this->payload($media)], 201);
    }

    public function update(Request $request, Media $media): JsonResponse
    {
        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $media->update($data);

        return response()->json(['media' => $this->payload($media)]);
    }

    /**
     * Refuses deletion while any section body still references the file.
     *
     * The row is deleted before the file on purpose: if the file delete then
     * fails, the worst case is a logged orphaned file on disk, never a
     * dangling row pointing at a missing file (a broken thumbnail in the UI).
     */
    public function destroy(Media $media): JsonResponse
    {
        $usedIn = $this->usedInLabels($media);

        if ($usedIn->isNotEmpty()) {
            $names = $usedIn->implode(', ');

            return response()->json([
                'message' => "File ini masih dipakai di konten: {$names}. Hapus dulu dari kontennya sebelum menghapus file.",
            ], 422);
        }

        $media->delete();

        $disk = Storage::disk('public');
        if (! $disk->delete($media->path) && $disk->exists($media->path)) {
            report(new RuntimeException("Media row {$media->id} deleted but file could not be removed from disk: {$media->path}"));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Labels of the sections referencing this file, e.g. "Belajar daring dan luring.".
     *
     * @return Collection<int, string>
     */
    private function usedInLabels(Media $media): Collection
    {
        return ContentSection::query()
            ->where('body', 'like', '%'.$media->path.'%')
            ->get(['page', 'heading'])
            ->map(fn (ContentSection $s) => $s->heading ?: ($s->page === 'community' ? 'Komunitas' : 'Program'))
            ->unique();
    }

    /** @return array{id:int,url:string,original_name:string,mime_type:string,size:int,alt_text:?string,is_image:bool,created_at:string} */
    private function payload(Media $media): array
    {
        return [
            'id' => $media->id,
            'url' => $media->url(),
            'original_name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'alt_text' => $media->alt_text,
            'is_image' => $media->isImage(),
            'created_at' => $media->created_at->toISOString(),
        ];
    }
}
