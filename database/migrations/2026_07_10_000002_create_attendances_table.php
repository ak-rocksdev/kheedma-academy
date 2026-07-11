<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A row IS "hadir" for that session; unmarking deletes the row, so
        // there is no updated_at (mirrors the append-only status_events).
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_session_id')->constrained('cohort_sessions')->cascadeOnDelete();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['cohort_session_id', 'enrollment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
