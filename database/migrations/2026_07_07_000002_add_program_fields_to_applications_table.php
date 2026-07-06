<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // program_id: nullable at the DB level (legacy rows predate programs)
        // but required by validation for every new submission.
        // referral_source: closes a Layer 1 gap — the v1 concept mandates
        // capturing how the applicant heard about the Academy.
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('people_id')->constrained('programs')->nullOnDelete();
            $table->string('referral_source')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->dropColumn('referral_source');
        });
    }
};
