<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decision 2026-07-12: an application targets a COHORT (the real class with
     * a schedule), resolved from the program's open cohort at submission time;
     * the program stays as the catalog grouping. review_note records the
     * reviewer's (optional) rejection reason.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('cohort_id')->nullable()->after('program_id')->constrained('cohorts')->nullOnDelete();
            $table->text('review_note')->nullable()->after('motivation');
        });

        $this->backfillCohorts();
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cohort_id');
            $table->dropColumn('review_note');
        });
    }

    /**
     * Best-effort cohort for pre-existing applications: the enrollment's cohort
     * when placed, else the program's cohort open right now (nearest start),
     * else the program's only cohort, else null.
     */
    private function backfillCohorts(): void
    {
        foreach (DB::table('enrollments')->whereNotNull('application_id')->get(['application_id', 'cohort_id']) as $enrollment) {
            DB::table('applications')->where('id', $enrollment->application_id)->update(['cohort_id' => $enrollment->cohort_id]);
        }

        $unresolved = DB::table('applications')->whereNull('cohort_id')->whereNotNull('program_id')->get(['id', 'program_id']);

        foreach ($unresolved as $application) {
            $openCohortId = DB::table('cohorts')
                ->where('program_id', $application->program_id)
                ->where(fn ($q) => $q->whereNotNull('registration_opens_at')->orWhereNotNull('registration_closes_at'))
                ->where(fn ($q) => $q->whereNull('registration_opens_at')->orWhere('registration_opens_at', '<=', now()))
                ->where(fn ($q) => $q->whereNull('registration_closes_at')->orWhere('registration_closes_at', '>=', now()))
                ->orderByRaw('start_date IS NULL, start_date ASC')
                ->orderByRaw('registration_closes_at IS NULL, registration_closes_at ASC')
                ->value('id');

            $cohortId = $openCohortId ?? $this->onlyCohortId($application->program_id);

            if ($cohortId !== null) {
                DB::table('applications')->where('id', $application->id)->update(['cohort_id' => $cohortId]);
            }
        }
    }

    private function onlyCohortId(int $programId): ?int
    {
        $cohortIds = DB::table('cohorts')->where('program_id', $programId)->limit(2)->pluck('id');

        return $cohortIds->count() === 1 ? $cohortIds->first() : null;
    }
};
