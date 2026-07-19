<?php

namespace App\Support;

use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\Person;
use App\Models\Program;

/**
 * Derived scoring rules (spec 2026-07-17): nothing here is stored. The
 * effective score is the latest GRADED submission; the average is per person
 * per program with missing assignments counted as zero; display and gate use
 * the SAME half-up 1-decimal rounding so they can never disagree.
 */
class AssignmentScoring
{
    public function effectiveScore(Assignment $assignment, Enrollment $enrollment): ?int
    {
        return $assignment->submissions()
            ->where('enrollment_id', $enrollment->id)
            ->whereNotNull('score')
            ->latest('id')
            ->value('score');
    }

    public function submissionState(Assignment $assignment, Enrollment $enrollment): string
    {
        $latest = $assignment->submissions()
            ->where('enrollment_id', $enrollment->id)
            ->latest('id')
            ->first();

        if ($latest === null) {
            return 'belum_dikerjakan';
        }

        return $latest->score === null ? 'menunggu_dinilai' : 'dinilai';
    }

    public function averageFor(Person $person, Program $program): ?float
    {
        $enrollments = $person->enrollments()
            ->whereHas('cohort', fn ($q) => $q->where('program_id', $program->id))
            ->with(['latestStatusEvent', 'cohort.sessions.assignment'])
            ->get()
            ->filter(fn (Enrollment $e) => $e->isActive());

        $scores = [];
        foreach ($enrollments as $enrollment) {
            foreach ($enrollment->cohort->sessions as $session) {
                if ($session->assignment !== null) {
                    $scores[] = $this->effectiveScore($session->assignment, $enrollment) ?? 0;
                }
            }
        }

        if ($scores === []) {
            return null;
        }

        return round(array_sum($scores) / count($scores), 1);
    }

    public function passes(Person $person, Program $program): bool
    {
        if ($program->min_average_score === null) {
            return false;
        }

        $average = $this->averageFor($person, $program);

        return $average !== null && $average >= $program->min_average_score;
    }
}
