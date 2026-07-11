<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Cohort;
use App\Models\CommunityMembership;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    /** Aggregate counts for the dashboard. Staff-only (role gate on the route). */
    public function index(): JsonResponse
    {
        $enrollments = Enrollment::with('latestStatusEvent')->get();

        return response()->json([
            'stats' => [
                'pending_applications' => Application::where('status', 'pending')->count(),
                'community_members' => CommunityMembership::count(),
                'active_cohorts' => Cohort::whereDate('start_date', '<=', now())
                    ->where(fn ($q) => $q->whereDate('end_date', '>=', now())->orWhereNull('end_date'))
                    ->count(),
                'active_participants' => $enrollments->filter(
                    fn (Enrollment $e) => ($e->latestStatusEvent?->status ?? 'accepted') === 'accepted'
                )->count(),
                // "Pernah hadir": orang unik dengan minimal satu kehadiran kelas.
                'attended_participants' => Enrollment::whereHas('attendances')
                    ->distinct()
                    ->count('people_id'),
            ],
        ]);
    }
}
