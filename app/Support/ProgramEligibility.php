<?php

namespace App\Support;

use App\Models\Person;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for who may access a program (spec 2026-07-08).
 *
 * Completion contract: a Person has completed a program when they have an
 * Enrollment in any Cohort of that program carrying a StatusEvent with
 * status 'completed'. Spec 2 (enrollment + attendance) writes that data;
 * this service only reads it.
 */
class ProgramEligibility
{
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
            return $this->hasCompleted($person, fn (Builder $q) => $q->where('type', 'general'))
                ? null
                : 'needs_general';
        }

        return $this->hasCompleted($person, fn (Builder $q) => $q->where('type', 'affiliate_community')->where('level', $level - 1))
            ? null
            : 'needs_previous_level';
    }

    /** @param  callable(Builder): Builder  $programScope */
    private function hasCompleted(Person $person, callable $programScope): bool
    {
        return $person->enrollments()
            ->whereHas('statusEvents', fn (Builder $q) => $q->where('status', 'completed'))
            ->whereHas('cohort.program', $programScope)
            ->exists();
    }
}
