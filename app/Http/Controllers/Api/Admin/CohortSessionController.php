<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CohortSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CohortSessionController extends Controller
{
    public function store(Request $request, Cohort $cohort): JsonResponse
    {
        $session = $cohort->sessions()->create($this->validated($request));

        return response()->json(['session' => $this->row($session)], 201);
    }

    public function update(Request $request, CohortSession $session): JsonResponse
    {
        $session->update($this->validated($request, $session));

        return response()->json(['session' => $this->row($session->fresh())]);
    }

    /** Deleting a session cascades its attendance records with it. */
    public function destroy(CohortSession $session): JsonResponse
    {
        $session->delete();

        return response()->json(null, 204);
    }

    /**
     * Venue rules mirror the old cohort-level ones (spec 2026-07-18): offline
     * requires a picked location; partial updates on legacy venueless sessions
     * must not brick.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CohortSession $session = null): array
    {
        $creating = $session === null;

        $isOffline = fn () => $request->input('type', $session?->type ?? 'offline') === 'offline';
        $locationTouched = $request->hasAny(['type', 'location_address', 'location_lat', 'location_lng']);
        $locationRequiredness = $locationTouched ? [Rule::requiredIf($isOffline), 'nullable'] : ['nullable'];

        return $request->validate([
            'title' => $creating ? ['required', 'string', 'max:255'] : ['sometimes', 'required', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'type' => [$creating ? 'required' : 'sometimes', 'required', Rule::in(['offline', 'online'])],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_address' => [...$locationRequiredness, 'string', 'max:500'],
            'location_lat' => [...$locationRequiredness, 'numeric', 'between:-90,90'],
            'location_lng' => [...$locationRequiredness, 'numeric', 'between:-180,180'],
            'meeting_url' => ['nullable', 'url:https', 'max:500'],
        ], [
            'location_address.required_if' => 'Kelas offline butuh alamat lokasi.',
            'location_lat.required_if' => 'Pilih titik lokasi dari pencarian tempat.',
            'location_lng.required_if' => 'Pilih titik lokasi dari pencarian tempat.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(CohortSession $s): array
    {
        return [
            'id' => $s->id,
            'title' => $s->title,
            'scheduled_at' => $s->scheduled_at?->toIso8601String(),
            'position' => (int) $s->position,
            'type' => $s->type,
            'location_name' => $s->location_name,
            'location_address' => $s->location_address,
            'location_lat' => $s->location_lat,
            'location_lng' => $s->location_lng,
            'meeting_url' => $s->meeting_url,
            'maps_url' => $s->mapsUrl(),
        ];
    }
}
