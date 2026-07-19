<?php

namespace App\Support;

use App\Models\Person;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for who may access a program (spec 2026-07-08,
 * re-amended 2026-07-17: the measure is the assignment-score average when the
 * prerequisite program carries a threshold AND has assignments; otherwise the
 * legacy attendance rule keeps governing — both the null-threshold case and
 * the "threshold set but no soal written yet" misconfiguration guard).
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

    /**
     * The person passes ANY prerequisite program matching the scope: by score
     * when that program gates on score and has assignments, else by attendance.
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
            if ($prerequisite->min_average_score !== null
                && $this->scoring->averageFor($person, $prerequisite) !== null) {
                if ($this->scoring->passes($person, $prerequisite)) {
                    return true;
                }

                continue; // Score rule governs this program; the bar was not met.
            }

            if ($this->hasAttended($person, $prerequisite)) {
                return true;
            }
        }

        return false;
    }

    private function hasAttended(Person $person, Program $program): bool
    {
        return $person->enrollments()
            ->whereHas('attendances')
            ->whereHas('cohort', fn (Builder $q) => $q->where('program_id', $program->id))
            ->exists();
    }
}
