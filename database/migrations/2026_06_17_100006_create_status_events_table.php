<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Status Event — an append-only list of transitions on an enrollment
        // (status + date + optional note), never a single overwritable status
        // field. One enrollment accumulates: accepted -> probation -> dropped
        // (reason). This delivers "what percentage drop, when, and why" without
        // destroying history. Rows are inserted, never updated (no updated_at).
        Schema::create('status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->string('status');                              // label is just data; free to change
            $table->text('note')->nullable();                     // e.g. reason for "dropped"
            $table->timestamp('occurred_at');                     // when the transition actually happened
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();        // when it was recorded (append-only: no updated_at)

            $table->index(['enrollment_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_events');
    }
};
