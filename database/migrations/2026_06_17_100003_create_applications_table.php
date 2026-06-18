<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Application — one submission of the form, belonging to one Person.
        // Three applications by the same person = one Person, three Applications.
        // The intake decision lives here as `status`; the pre-filter task result
        // is stored loosely (link + admin verdict) so the schema does not assume
        // a task format that is not yet fixed.
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('people')->cascadeOnDelete();
            $table->string('status')->default('pending');        // pending | accepted | rejected
            $table->boolean('prefilter_submitted')->default(false);
            $table->string('prefilter_link')->nullable();
            $table->string('prefilter_verdict')->nullable();      // pending | approved | rejected
            $table->text('prefilter_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
