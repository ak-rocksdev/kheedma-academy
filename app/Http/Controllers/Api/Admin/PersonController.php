<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\JsonResponse;

class PersonController extends Controller
{
    /** A person's profile plus their full cross-attempt history. */
    public function show(Person $person): JsonResponse
    {
        $person->load([
            'province:code,name',
            'city:code,name',
            'applications' => fn ($q) => $q->latest(),
            'applications.program:id,name',
            'enrollments.cohort:id,name,start_date,end_date',
            'enrollments.latestStatusEvent',
        ]);

        // Oldest submission = attempt #1; the list itself stays newest-first.
        $total = $person->applications->count();

        return response()->json([
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'email' => $person->email,
                'province' => $person->province?->name,
                'city' => $person->city?->name,
                'tiktok_username' => $person->tiktok_username,
                'instagram_username' => $person->instagram_username,
                'created_at' => $person->created_at?->toIso8601String(),
                'applications' => $person->applications->values()->map(fn ($a, $index) => [
                    'id' => $a->id,
                    'attempt' => $total - $index,
                    'program' => $a->program?->name,
                    'status' => $a->status,
                    'prefilter_submitted' => (bool) $a->prefilter_submitted,
                    'prefilter_link' => $a->prefilter_link,
                    'prefilter_verdict' => $a->prefilter_verdict,
                    'prefilter_note' => $a->prefilter_note,
                    'reviewed_at' => $a->reviewed_at?->toIso8601String(),
                    'created_at' => $a->created_at?->toIso8601String(),
                ]),
                'enrollments' => $person->enrollments->map(fn ($e) => [
                    'id' => $e->id,
                    'cohort' => $e->cohort?->name,
                    'latest_status' => $e->latestStatusEvent?->status,
                    'latest_status_at' => $e->latestStatusEvent?->occurred_at?->toIso8601String(),
                ]),
            ],
        ]);
    }
}
