<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Community membership — the unselective second door. One row per
        // Person, ever; created_at is the join timestamp. The login account
        // itself lives on users (linked via people.user_id).
        Schema::create('community_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('people_id')->unique()->constrained('people')->cascadeOnDelete();
            $table->string('referral_source')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_memberships');
    }
};
