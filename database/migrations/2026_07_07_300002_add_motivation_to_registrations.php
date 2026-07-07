<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-registration intent ("kenapa ingin ikut/gabung") — belongs to the
        // registration record, not the Person. Nullable for legacy rows.
        Schema::table('applications', function (Blueprint $table) {
            $table->text('motivation')->nullable()->after('referral_source');
        });
        Schema::table('community_memberships', function (Blueprint $table) {
            $table->text('motivation')->nullable()->after('referral_source');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('motivation');
        });
        Schema::table('community_memberships', function (Blueprint $table) {
            $table->dropColumn('motivation');
        });
    }
};
