<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional class cover image. When null the UI renders the generative
        // brand cover (gradient + supergraphic + arch monogram) instead, so a
        // program never ships without a face. Admin upload arrives later.
        Schema::table('programs', function (Blueprint $table) {
            $table->string('thumbnail_path')->nullable()->after('locked_message');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn('thumbnail_path');
        });
    }
};
