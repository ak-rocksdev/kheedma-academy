<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Decision 2026-07-11: the person-merge feature is removed (duplicate
     * applications are now prevented at the funnel; merge stays recoverable
     * from git history if the changed-phone-number case ever becomes real).
     * Drops its only schema trace and the orphaned permission row.
     */
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
        });

        DB::table('permissions')->where('name', 'people.merge')->delete();
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('merged_into_id')->nullable()->constrained('people')->nullOnDelete();
        });
    }
};
