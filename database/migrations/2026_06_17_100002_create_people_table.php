<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Person — one durable record per real human, ever. The identity anchor
        // is the phone number; the admin "merge two people" flow soft-deletes a
        // duplicate and repoints its relations onto the surviving record.
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();          // anchor, normalised to +62
            $table->string('email')->unique();          // required secondary (comms / future login)
            $table->char('province_code', 2)->nullable();
            $table->char('city_code', 4)->nullable();
            $table->string('tiktok_username')->nullable();
            $table->string('instagram_username')->nullable();
            // Optional link to a login account. A Person exists without one (the
            // current state); a User (role: participant) is provisioned and linked
            // only when the participant needs to log in later.
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('province_code')->references('code')->on('indonesia_provinces')->nullOnDelete();
            $table->foreign('city_code')->references('code')->on('indonesia_cities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
