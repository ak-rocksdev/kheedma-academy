<?php

namespace App\Support;

use App\Models\Cohort;
use App\Models\Enrollment;

/**
 * Derives completion from attendance — the ONLY writer of system-authored
 * completed StatusEvents (note 'auto:attendance', created_by null). Manual
 * events are never modified or deleted. Spec 2026-07-10.
 */
class AttendanceCompletion
{
    private const MARKER = 'auto:attendance';

    /** Recompute one enrollment after any attendance change. */
    public function sync(Enrollment $enrollment): void
    {
        $cohort = $enrollment->cohort;
        $requirement = $cohort->required_attendance ?? $cohort->sessions()->count();

        if ($requirement === 0) {
            return;
        }

        if ($enrollment->latestStatusEvent()->first()?->status === 'dropped') {
            return;
        }

        $hadir = $enrollment->attendances()->count();
        $hasAutoEvent = $enrollment->statusEvents()
            ->where('status', 'completed')->where('note', self::MARKER)->exists();

        if ($hadir >= $requirement && ! $hasAutoEvent) {
            $enrollment->statusEvents()->create([
                'status' => 'completed',
                'note' => self::MARKER,
                'occurred_at' => now(),
                'created_by' => null,
            ]);
        }

        if ($hadir < $requirement && $hasAutoEvent) {
            $enrollment->statusEvents()
                ->where('status', 'completed')->where('note', self::MARKER)->delete();
        }
    }

    /** Recompute every enrollment of a cohort (after a session is deleted). */
    public function syncCohort(Cohort $cohort): void
    {
        $cohort->enrollments()->with('cohort')->get()->each(fn (Enrollment $e) => $this->sync($e));
    }
}
