<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Launch decision 2026-07-15: one cohort = one meeting, seeded invisibly.
     * Cohorts created before this rule get their default session here so
     * attendance can be recorded for them right away.
     */
    public function up(): void
    {
        $cohorts = DB::table('cohorts')
            ->whereNotIn('id', DB::table('cohort_sessions')->select('cohort_id'))
            ->get(['id', 'start_date']);

        foreach ($cohorts as $cohort) {
            DB::table('cohort_sessions')->insert([
                'cohort_id' => $cohort->id,
                'title' => 'Pertemuan',
                'scheduled_at' => $cohort->start_date,
                'position' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Data seeding is not reversible.
    }
};
