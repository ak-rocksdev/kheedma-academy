<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Minimum "hadir" count to auto-complete. Null = all sessions.
        Schema::table('cohorts', function (Blueprint $table) {
            $table->unsignedTinyInteger('required_attendance')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn('required_attendance');
        });
    }
};
