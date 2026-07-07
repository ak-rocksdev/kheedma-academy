<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('gender', 10)->nullable()->after('birth_date');
            $table->boolean('followed_socials')->nullable()->after('affiliate_gmv_range');
        });
    }

    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['gender', 'followed_socials']);
        });
    }
};
