<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Decision 2026-07-10: no "lulus" status for now — the only measure is
        // "pernah diikuti" (has attendance). Eligibility reads attendance
        // directly, so the per-cohort completion threshold goes away and
        // system-authored completion events (dev sample data only) are purged.
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn('required_attendance');
        });

        DB::table('status_events')
            ->where('status', 'completed')
            ->where('note', 'auto:attendance')
            ->delete();
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->unsignedTinyInteger('required_attendance')->nullable()->after('end_date');
        });
    }
};
