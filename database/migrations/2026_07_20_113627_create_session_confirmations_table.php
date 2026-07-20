<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('session_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['cohort_session_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('session_confirmations');
    }
};
