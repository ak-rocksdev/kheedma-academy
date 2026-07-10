<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catalog segmentation (spec 2026-07-08): general classes vs tiered
        // affiliate-community classes. Existing rows are general by default.
        Schema::table('programs', function (Blueprint $table) {
            $table->string('type')->default('general')->after('description');   // general | affiliate_community
            $table->unsignedTinyInteger('level')->nullable()->after('type');    // affiliate tier; null for general
            $table->text('locked_message')->nullable()->after('level');         // per-class lock popup copy
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['type', 'level', 'locked_message']);
        });
    }
};
