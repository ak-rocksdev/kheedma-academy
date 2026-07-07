<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicantController extends Controller
{
    /** Paginated, searchable list of applications (one row per submission). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,accepted,rejected'],
            'program' => ['nullable', 'integer', 'exists:programs,id'],
        ]);

        $applications = Application::query()
            ->with([
                'person' => fn ($q) => $q->select('id', 'name', 'phone', 'email', 'city_code')->withCount('applications'),
                'person.city:code,name',
                'program:id,name',
            ])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('program'), fn ($q) => $q->where('program_id', $request->integer('program')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->whereHas('person', function ($p) use ($term) {
                    $p->where('name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Application $a) => $this->row($a));

        return response()->json($applications);
    }

    /** Record the intake decision / pre-filter task result on an application. */
    public function update(Request $request, Application $application): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:pending,accepted,rejected'],
            'prefilter_submitted' => ['sometimes', 'boolean'],
            'prefilter_link' => ['sometimes', 'nullable', 'url', 'max:255'],
            'prefilter_verdict' => ['sometimes', 'nullable', 'in:pending,approved,rejected'],
            'prefilter_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('status', $data)) {
            $data['reviewed_at'] = $data['status'] === 'pending' ? null : now();
        }

        $application->update($data);

        return response()->json(['application' => $this->row($application->fresh('person.city'))]);
    }

    private function row(Application $a): array
    {
        return [
            'id' => $a->id,
            'status' => $a->status,
            'prefilter_submitted' => (bool) $a->prefilter_submitted,
            'prefilter_verdict' => $a->prefilter_verdict,
            'created_at' => $a->created_at?->toIso8601String(),
            'program' => $a->program?->name,
            'referral_source' => $a->referral_source,
            'person' => [
                'id' => $a->person->id,
                'name' => $a->person->name,
                'phone' => $a->person->phone,
                'email' => $a->person->email,
                'city' => $a->person->city?->name,
                // Loaded on index; the update path reloads without the count (null there).
                'applications_count' => $a->person->applications_count ?? null,
            ],
        ];
    }
}
