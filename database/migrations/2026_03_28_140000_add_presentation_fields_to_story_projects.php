<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_projects', function (Blueprint $table) {
            $table->boolean('flip_gameplay_enabled')->default(true)->after('meta');
            $table->json('cover_front')->nullable()->after('flip_gameplay_enabled');
            $table->json('cover_back')->nullable()->after('cover_front');
        });
    }

    public function down(): void
    {
        Schema::table('story_projects', function (Blueprint $table) {
            $table->dropColumn(['flip_gameplay_enabled', 'cover_front', 'cover_back']);
        });
    }
};
