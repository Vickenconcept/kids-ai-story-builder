<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_projects', function (Blueprint $table) {
            $table->json('flip_settings')->nullable()->after('sharing_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('story_projects', function (Blueprint $table) {
            $table->dropColumn('flip_settings');
        });
    }
};
