<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The pre-filter task verdict returns later as a richer feature; the
        // simple columns are removed to keep the reviewed structure honest.
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['prefilter_submitted', 'prefilter_link', 'prefilter_verdict', 'prefilter_note']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->boolean('prefilter_submitted')->default(false);
            $table->string('prefilter_link')->nullable();
            $table->string('prefilter_verdict')->nullable();
            $table->text('prefilter_note')->nullable();
        });
    }
};
