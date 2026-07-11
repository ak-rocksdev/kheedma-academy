<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Policy (2026-07-11): one ACTIVE application (pending/accepted) per
     * person per program. The funnel now enforces this; this migration fixes
     * rows created before the guard. Kept row: accepted first, then the
     * earliest submission. Rejected rows are history and stay untouched.
     */
    public function up(): void
    {
        $duplicates = DB::table('applications')
            ->select('people_id', 'program_id')
            ->whereIn('status', ['pending', 'accepted'])
            ->whereNotNull('program_id')
            ->groupBy('people_id', 'program_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $group) {
            $keepId = DB::table('applications')
                ->where('people_id', $group->people_id)
                ->where('program_id', $group->program_id)
                ->whereIn('status', ['pending', 'accepted'])
                ->orderByRaw("status = 'accepted' desc")
                ->orderBy('created_at')
                ->orderBy('id')
                ->value('id');

            DB::table('applications')
                ->where('people_id', $group->people_id)
                ->where('program_id', $group->program_id)
                ->whereIn('status', ['pending', 'accepted'])
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }

    public function down(): void
    {
        // Data cleanup is not reversible.
    }
};
