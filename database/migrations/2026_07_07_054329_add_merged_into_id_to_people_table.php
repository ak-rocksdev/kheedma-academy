<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Merge audit trail: a merged (soft-deleted) duplicate records which
        // Person absorbed it. `deleted_at` doubles as the merge timestamp; the
        // duplicate's phone/email are mangled to inert tombstone values so the
        // real identity becomes reusable despite the plain unique indexes.
        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('merged_into_id')->nullable()->after('user_id')->constrained('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
