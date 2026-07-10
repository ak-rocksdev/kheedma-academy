<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cohort;
use App\Models\CohortSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CohortSessionController extends Controller
{
    public function store(Request $request, Cohort $cohort): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['nullable', 'date'],
            'position' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $session = $cohort->sessions()->create($data);

        return response()->json(['session' => $this->row($session)], 201);
    }

    public function update(Request $request, CohortSession $session): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'position' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $session->update($data);

        return response()->json(['session' => $this->row($session->fresh())]);
    }

    /** Deleting a session cascades its attendance records with it. */
    public function destroy(CohortSession $session): JsonResponse
    {
        $session->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array{id:int,title:string,scheduled_at:?string,position:int}
     */
    private function row(CohortSession $s): array
    {
        return [
            'id' => $s->id,
            'title' => $s->title,
            'scheduled_at' => $s->scheduled_at?->toIso8601String(),
            'position' => (int) $s->position,
        ];
    }
}
