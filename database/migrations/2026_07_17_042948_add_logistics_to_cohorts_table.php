<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Class start becomes a datetime: with a time-of-day it can serve as a
        // hard auto-close ceiling for registration. Logistics columns support
        // offline (location) and online (meeting link) cohorts.
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dateTime('start_date')->nullable()->change();
            $table->string('type')->default('offline'); // 'offline' | 'online'
            $table->string('location_name')->nullable();
            $table->string('location_address')->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->string('meeting_url')->nullable();
            $table->string('materials_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'location_name',
                'location_address',
                'location_lat',
                'location_lng',
                'meeting_url',
                'materials_url',
            ]);
        });

        Schema::table('cohorts', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
        });
    }
};
