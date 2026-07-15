<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApplicantController extends Controller
{
    /** Paginated, searchable list of applications (one row per submission). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:pending,accepted,rejected'],
            'program' => ['nullable', 'integer', 'exists:programs,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $applications = Application::query()
            ->with([
                'person' => fn ($q) => $q->select('id', 'name', 'phone', 'email', 'city_code')->withCount('applications'),
                'person.city:code,name',
                'program:id,name',
                'cohort:id,name',
                'enrollment.cohort:id,name',
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
            ->paginate($request->integer('per_page') ?: 15)
            ->withQueryString()
            ->through(fn (Application $a) => $this->row($a));

        return response()->json($applications);
    }

    /**
     * Record the intake decision. Accepting an application that targets a
     * cohort also places the person there (enrollment + status event) in the
     * same transaction: "diterima" means "punya kursi", not a limbo state.
     */
    public function update(Request $request, Application $application): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:pending,accepted,rejected'],
            'review_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (array_key_exists('status', $data)) {
            $data['reviewed_at'] = $data['status'] === 'pending' ? null : now();
        }

        DB::transaction(function () use ($request, $application, $data) {
            $wasAccepted = $application->status === 'accepted';

            $application->update($data);

            $becameAccepted = ($data['status'] ?? null) === 'accepted' && ! $wasAccepted;
            $alreadyPlaced = $application->cohort_id !== null && Enrollment::query()
                ->where('people_id', $application->people_id)
                ->where('cohort_id', $application->cohort_id)
                ->exists();

            if ($becameAccepted && $application->cohort_id !== null && ! $alreadyPlaced) {
                $enrollment = Enrollment::create([
                    'people_id' => $application->people_id,
                    'cohort_id' => $application->cohort_id,
                    'application_id' => $application->id,
                ]);
                $enrollment->statusEvents()->create([
                    'status' => 'accepted',
                    'occurred_at' => now(),
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        return response()->json([
            'application' => $this->row($application->fresh(['person.city', 'program:id,name', 'cohort:id,name', 'enrollment.cohort:id,name'])),
        ]);
    }

    private function row(Application $a): array
    {
        return [
            'id' => $a->id,
            'status' => $a->status,
            'created_at' => $a->created_at?->toIso8601String(),
            'program' => $a->program?->name,
            'cohort' => $a->cohort ? ['id' => $a->cohort->id, 'name' => $a->cohort->name] : null,
            'referral_source' => $a->referral_source,
            'review_note' => $a->review_note,
            'enrollment' => $a->enrollment ? [
                'cohort_id' => $a->enrollment->cohort_id,
                'cohort_name' => $a->enrollment->cohort?->name,
            ] : null,
            'person' => $a->person ? [
                'id' => $a->person->id,
                'name' => $a->person->name,
                'phone' => $a->person->phone,
                'email' => $a->person->email,
                'city' => $a->person->city?->name,
                // Loaded on index; the update path reloads without the count (null there).
                'applications_count' => $a->person->applications_count ?? null,
            ] : null,
        ];
    }
}
