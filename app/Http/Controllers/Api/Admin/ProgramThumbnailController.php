<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProgramThumbnailController extends Controller
{
    /** Upload/replace the class cover; the old file never lingers. */
    public function store(Request $request, Program $program): JsonResponse
    {
        $request->validate([
            'thumbnail' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ]);

        if ($program->thumbnail_path) {
            Storage::disk('public')->delete($program->thumbnail_path);
        }

        $path = $request->file('thumbnail')->store('programs', 'public');
        $program->update(['thumbnail_path' => $path]);

        return response()->json(['program' => $this->payload($program)]);
    }

    /** Remove the cover; the public card falls back to the generative cover. */
    public function destroy(Program $program): JsonResponse
    {
        if ($program->thumbnail_path) {
            Storage::disk('public')->delete($program->thumbnail_path);
            $program->update(['thumbnail_path' => null]);
        }

        return response()->json(['program' => $this->payload($program)]);
    }

    /**
     * @return array{id:int,thumbnail_url:?string}
     */
    private function payload(Program $program): array
    {
        $program->refresh();

        return [
            'id' => $program->id,
            'thumbnail_url' => $program->thumbnail_path ? Storage::disk('public')->url($program->thumbnail_path) : null,
        ];
    }
}
