<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cohort = batch, session = class (spec 2026-07-18). Venue is a property
     * of the meeting, not the batch — a Saturday offline class can move to a
     * Friday-night online one. Values are copied onto ALL existing sessions
     * (venue was cohort-wide truth until now), then the cohort columns drop.
     */
    public function up(): void
    {
        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->string('type')->nullable(); // 'offline' | 'online'
            $table->string('location_name')->nullable();
            $table->string('location_address')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('meeting_url')->nullable();
        });

        // Correlated subqueries instead of an UPDATE...JOIN: MySQL supports
        // both, but the test suite runs on SQLite (see phpunit.xml), which
        // rejects table aliases in UPDATE...JOIN. This form is portable.
        DB::statement(<<<'SQL'
            UPDATE cohort_sessions
            SET type = (SELECT type FROM cohorts WHERE cohorts.id = cohort_sessions.cohort_id),
                location_name = (SELECT location_name FROM cohorts WHERE cohorts.id = cohort_sessions.cohort_id),
                location_address = (SELECT location_address FROM cohorts WHERE cohorts.id = cohort_sessions.cohort_id),
                location_lat = (SELECT location_lat FROM cohorts WHERE cohorts.id = cohort_sessions.cohort_id),
                location_lng = (SELECT location_lng FROM cohorts WHERE cohorts.id = cohort_sessions.cohort_id),
                meeting_url = (SELECT meeting_url FROM cohorts WHERE cohorts.id = cohort_sessions.cohort_id)
            WHERE EXISTS (SELECT 1 FROM cohorts WHERE cohorts.id = cohort_sessions.cohort_id)
        SQL);

        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn(['type', 'location_name', 'location_address', 'location_lat', 'location_lng', 'meeting_url']);
        });
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->string('type')->default('offline');
            $table->string('location_name')->nullable();
            $table->string('location_address')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('meeting_url')->nullable();
        });

        // Best effort: restore each cohort's venue from its first session.
        // Portable correlated subqueries for the same reason as up() above.
        DB::statement(<<<'SQL'
            UPDATE cohorts
            SET type = COALESCE((SELECT type FROM cohort_sessions WHERE cohort_sessions.cohort_id = cohorts.id ORDER BY position, id LIMIT 1), 'offline'),
                location_name = (SELECT location_name FROM cohort_sessions WHERE cohort_sessions.cohort_id = cohorts.id ORDER BY position, id LIMIT 1),
                location_address = (SELECT location_address FROM cohort_sessions WHERE cohort_sessions.cohort_id = cohorts.id ORDER BY position, id LIMIT 1),
                location_lat = (SELECT location_lat FROM cohort_sessions WHERE cohort_sessions.cohort_id = cohorts.id ORDER BY position, id LIMIT 1),
                location_lng = (SELECT location_lng FROM cohort_sessions WHERE cohort_sessions.cohort_id = cohorts.id ORDER BY position, id LIMIT 1),
                meeting_url = (SELECT meeting_url FROM cohort_sessions WHERE cohort_sessions.cohort_id = cohorts.id ORDER BY position, id LIMIT 1)
        SQL);

        Schema::table('cohort_sessions', function (Blueprint $table) {
            $table->dropColumn(['type', 'location_name', 'location_address', 'location_lat', 'location_lng', 'meeting_url']);
        });
    }
};
