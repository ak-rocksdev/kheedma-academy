<?php

namespace App\Support;

use App\Models\Person;
use App\Models\Program;
use Illuminate\Database\Eloquent\Builder;

/**
 * Single source of truth for who may access a program (spec 2026-07-08,
 * amended 2026-07-10: no "lulus" status — the measure is attendance).
 *
 * Contract: a Person has taken a program when they have an Enrollment in any
 * Cohort of that program with at least one attendance ("pernah diikuti").
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
            return $this->hasAttended($person, fn (Builder $q) => $q->where('type', 'general'))
                ? null
                : 'needs_general';
        }

        return $this->hasAttended($person, fn (Builder $q) => $q->where('type', 'affiliate_community')->where('level', $level - 1))
            ? null
            : 'needs_previous_level';
    }

    /** @param  callable(Builder): Builder  $programScope */
    private function hasAttended(Person $person, callable $programScope): bool
    {
        return $person->enrollments()
            ->whereHas('attendances')
            ->whereHas('cohort.program', $programScope)
            ->exists();
    }
}
