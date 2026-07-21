<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for who may access a program (spec 2026-07-08,
 * re-amended 2026-07-21: the prerequisite is COMPLETING an intake - attending
 * every class of at least one cohort - not merely showing up once. When the
 * prerequisite program also carries a score threshold AND has soal to grade,
 * the average must clear the bar as well; a threshold with no assignments yet
 * stays governed by completion alone (the misconfiguration guard).
 */
class ProgramEligibility
{
    public function __construct(private readonly AssignmentScoring $scoring) {}

    public function canAccess(?Person $person, Program $program): bool
    {
        return $this->lockReason($person, $program) === null;
    }

    /** null when accessible; otherwise guest | needs_general | needs_previous_level. */
    public function lockReason(?Person $person, Program $program): ?string
    {
        if (! $program->isAffiliate()) {
            return null;
        }

        if ($person === null) {
            return 'guest';
        }

        $level = $program->level ?? 1;

        if ($level <= 1) {
            return $this->passesAny($person, fn (Builder $q) => $q->where('type', 'general'))
                ? null
                : 'needs_general';
        }

        return $this->passesAny($person, fn (Builder $q) => $q->where('type', 'affiliate_community')->where('level', $level - 1))
            ? null
            : 'needs_previous_level';
    }

    /** Whether the person may join the affiliate community (become a member). */
    public function canJoinCommunity(?Person $person): bool
    {
        return $this->joinLockReason($person) === null;
    }

    /**
     * null when the person may join; otherwise guest | needs_general.
     * Joining requires completing a Program Umum intake (attending every class
     * of one cohort) and, where that program sets a score bar with soal,
     * clearing it - the same measure `passesAny` applies over general programs.
     */
    public function joinLockReason(?Person $person): ?string
    {
        if ($person === null) {
            return 'guest';
        }

        return $this->passesAny($person, fn (Builder $q) => $q->where('type', 'general'))
            ? null
            : 'needs_general';
    }

    /**
     * The person passes ANY prerequisite program matching the scope: they must
     * have COMPLETED an intake of it (attended every class of one cohort), and
     * where that program sets a score bar and has soal, clear the bar too.
     *
     * @param  callable(Builder): Builder  $programScope
     */
    private function passesAny(Person $person, callable $programScope): bool
    {
        $programs = Program::query()
            ->tap(fn (Builder $q) => $programScope($q))
            ->whereHas('cohorts.enrollments', fn (Builder $q) => $q->where('people_id', $person->id))
            ->get();

        foreach ($programs as $prerequisite) {
            if (! $this->hasCompletedACohort($person, $prerequisite)) {
                continue;
            }

            // The score bar only bites when it is set AND there are soal to
            // grade: a null average means no assignments exist yet, so
            // completion alone governs (misconfiguration guard).
            if ($prerequisite->min_average_score !== null) {
                $average = $this->scoring->averageFor($person, $prerequisite);

                if ($average !== null && $average < $prerequisite->min_average_score) {
                    continue;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Completed = an active enrollment in some cohort of the program whose every
     * session the member attended. A cohort with no sessions cannot be
     * completed.
     */
    private function hasCompletedACohort(Person $person, Program $program): bool
    {
        $enrollments = $person->enrollments()
            ->whereHas('cohort', fn (Builder $q) => $q->where('program_id', $program->id))
            ->with(['cohort.sessions:id,cohort_id', 'attendances:id,enrollment_id,cohort_session_id', 'latestStatusEvent'])
            ->get()
            ->filter(fn (Enrollment $e) => $e->isActive());

        foreach ($enrollments as $enrollment) {
            $sessionIds = $enrollment->cohort->sessions->pluck('id');

            if ($sessionIds->isEmpty()) {
                continue;
            }

            $attendedIds = $enrollment->attendances->pluck('cohort_session_id')->unique();

            if ($sessionIds->diff($attendedIds)->isEmpty()) {
                return true;
            }
        }

        return false;
    }
}
