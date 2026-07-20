<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Enrollment;
use App\Models\Program;
use App\Support\AssignmentScoring;
use App\Support\ProgramEligibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MemberAreaController extends Controller
{
    /** Minimal member home: identity + membership; products/announcements land later. */
    public function index(Request $request, AssignmentScoring $scoring): View|RedirectResponse
    {
        $user = $request->user();

        // Mid-session deactivation must end the session here too (the admin
        // API enforces this via EnsureUserIsActive; the member area is the
        // web counterpart).
        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('member.login');
        }

        if ($user->hasAnyRole(['admin', 'mentor'])) {
            return redirect('/admin');
        }

        $person = $user->person()->with([
            'communityMembership',
            'applications' => fn ($q) => $q->latest(),
            'applications.program:id,name',
            'applications.cohort:id,name,start_date',
        ])->first();

        $eligibility = app(ProgramEligibility::class);
        $affiliate = Program::query()
            ->where('status', 'active')
            ->where('type', 'affiliate_community')
            ->orderBy('level')
            ->get()
            ->map(fn (Program $program) => [
                'program' => $program,
                'locked' => ! $eligibility->canAccess($person, $program),
                'reason' => $eligibility->lockReason($person, $program),
                'message' => $program->locked_message ?? config('kheedma.default_locked_message'),
            ]);

        // General classes currently open for registration: the member-area door
        // into the same funnel form (which short-circuits to a confirm card for
        // logged-in members). An active relation replaces the CTA with a chip.
        $openClasses = Program::query()
            ->openForRegistration()
            ->where('type', 'general')
            ->latest()
            ->get()
            ->map(function (Program $program) use ($person) {
                $state = $person?->applicationStateFor($program) ?? 'none';

                return [
                    'program' => $program,
                    'openCohort' => $program->openCohort(),
                    'state' => $state,
                    'chip' => $this->stateChip($state),
                ];
            });

        // Kelasmu: the member's own active enrollments, with cohort logistics.
        $enrolledClasses = $person
            ? $person->enrollments()
                ->with(['cohort.program', 'cohort.mentor:id,name', 'cohort.sessions.assignment', 'latestStatusEvent'])
                ->withCount('attendances')
                ->get()
                ->filter(fn (Enrollment $e) => $e->isActive())
                ->values()
            : collect();

        // Assignment cards, keyed by session id: everything the Blade shows is
        // computed here (house rule: view logic lives in the controller).
        $assignmentCards = [];
        foreach ($enrolledClasses as $enrollment) {
            foreach ($enrollment->cohort->sessions as $session) {
                if ($session->assignment === null) {
                    continue;
                }
                $assignment = $session->assignment;
                $rows = $assignment->submissions()
                    ->where('enrollment_id', $enrollment->id)
                    ->orderByDesc('id')
                    ->get();
                $latest = $rows->first();
                $lastGraded = $rows->whereNotNull('score')->first();

                $assignmentCards[$session->id] = [
                    'assignment' => $assignment,
                    'state' => $scoring->submissionState($assignment, $enrollment),
                    'score' => $lastGraded?->score,
                    'feedback' => $lastGraded?->feedback,
                    'latest_url' => $latest?->url,
                    'latest_at' => $latest?->created_at,
                    'versions' => $rows->count(),
                    // Compact own history for the card (spec: versions with time +
                    // grade if any), oldest last.
                    'history' => $rows->map(fn ($s) => [
                        'url' => $s->url,
                        'at' => $s->created_at,
                        'score' => $s->score,
                    ])->values()->all(),
                ];
            }
        }

        // Progress per enrolled program that carries a threshold: the average is
        // per person per program (AssignmentScoring), same rounded value the gate
        // compares. rows = every assignment across the person's active
        // enrollments of that program.
        $programProgress = $enrolledClasses
            ->groupBy(fn ($e) => $e->cohort->program_id)
            ->map(function ($group) use ($person, $scoring) {
                $program = $group->first()->cohort->program;
                if ($program->min_average_score === null) {
                    return null;
                }
                $rows = [];
                foreach ($group as $enrollment) {
                    foreach ($enrollment->cohort->sessions as $session) {
                        if ($session->assignment === null) {
                            continue;
                        }
                        $rows[] = [
                            'title' => $session->title,
                            'state' => $scoring->submissionState($session->assignment, $enrollment),
                            'score' => $scoring->effectiveScore($session->assignment, $enrollment),
                        ];
                    }
                }
                $average = $scoring->averageFor($person, $program);

                return [
                    'program' => $program,
                    'average' => $average,
                    'threshold' => (int) $program->min_average_score,
                    'qualifies' => $average !== null && $average >= $program->min_average_score,
                    'rows' => $rows,
                ];
            })
            ->filter()
            ->values();

        // Kelas tab: what to say when there is no enrolled class yet — an
        // in-review or accepted-awaiting-placement member must never face a
        // silent, unexplained gap.
        $applicationsRaw = $person?->applications ?? collect();
        $kelasNotice = null;
        if ($enrolledClasses->isEmpty()) {
            if ($applicationsRaw->contains(fn ($a) => $a->status === 'pending')) {
                $kelasNotice = 'pending';
            } elseif ($applicationsRaw->contains(fn ($a) => $a->status === 'accepted')) {
                $kelasNotice = 'accepted';
            }
        }

        // Tab aktif dari query ?bagian=; nilai tak dikenal jatuh ke default.
        // Smart default: member dengan kelas aktif datang untuk kelasnya —
        // sambut langsung di tab kelas, bukan profil.
        $activeTab = in_array($request->query('bagian'), ['profil', 'pendaftaran', 'kelas'], true)
            ? $request->query('bagian')
            : ($enrolledClasses->isNotEmpty() ? 'kelas' : 'profil');

        return view('member.akun', [
            'user' => $user,
            'person' => $person,
            'membership' => $person?->communityMembership,
            'applications' => ($person?->applications ?? collect())->map(fn ($a) => $this->applicationCard($a)),
            'affiliate' => $affiliate,
            'openClasses' => $openClasses,
            'enrolledClasses' => $enrolledClasses,
            'assignmentCards' => $assignmentCards,
            'programProgress' => $programProgress,
            'kelasNotice' => $kelasNotice,
            'activeTab' => $activeTab,
        ]);
    }

    /** Chip text for an open-class card; null = no active relation, show the CTA. */
    private function stateChip(string $state): ?string
    {
        return match ($state) {
            'pending' => 'Menunggu review',
            'accepted' => 'Kamu diterima',
            'enrolled' => 'Kamu peserta',
            default => null,
        };
    }

    /**
     * Presentation of one application row on the status card (label + badge
     * classes computed here, not in the Blade).
     *
     * @return array<string, mixed>
     */
    private function applicationCard(Application $application): array
    {
        return [
            'program' => $application->program?->name ?? 'Program',
            'cohort' => $application->cohort?->name,
            'created_at' => $application->created_at,
            'status' => $application->status,
            'statusLabel' => match ($application->status) {
                'pending' => 'Menunggu',
                'accepted' => 'Diterima',
                'rejected' => 'Belum lolos',
                default => $application->status,
            },
            'statusClass' => match ($application->status) {
                'pending' => 'bg-orange-100 text-orange-700',
                'accepted' => 'bg-teal-100 text-teal-700',
                'rejected' => 'bg-red-50 text-red-600',
                default => 'bg-sand-100 text-teal-800/70',
            },
            'reviewNote' => $application->status === 'rejected' ? $application->review_note : null,
        ];
    }
}
