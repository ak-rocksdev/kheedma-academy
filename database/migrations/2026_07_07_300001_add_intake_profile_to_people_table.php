<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Intake profile (from the legacy Google Form): refreshed on every
        // registration touch. The affiliate block is gated by tiktok_username
        // at the validation layer; columns are nullable for legacy rows.
        Schema::table('people', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('email');
            $table->unsignedInteger('tiktok_followers')->nullable()->after('tiktok_username');
            $table->boolean('has_started_affiliate')->nullable()->after('tiktok_followers');
            $table->unsignedTinyInteger('affiliate_level')->nullable()->after('has_started_affiliate');
            $table->string('affiliate_gmv_range', 10)->nullable()->after('affiliate_level');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'tiktok_followers', 'has_started_affiliate', 'affiliate_level', 'affiliate_gmv_range']);
        });
    }
};
