<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Model correction: the registration window belongs to the intake
        // (Angkatan), not the catalog item. "Program open" is derived from its
        // cohorts. Dev-only values on programs are intentionally dropped.
        Schema::table('cohorts', function (Blueprint $table) {
            $table->timestamp('registration_opens_at')->nullable()->after('end_date');
            $table->timestamp('registration_closes_at')->nullable()->after('registration_opens_at');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn(['registration_opens_at', 'registration_closes_at']);
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->timestamp('registration_opens_at')->nullable()->after('status');
            $table->timestamp('registration_closes_at')->nullable()->after('registration_opens_at');
        });

        Schema::table('cohorts', function (Blueprint $table) {
            $table->dropColumn(['registration_opens_at', 'registration_closes_at']);
        });
    }
};
