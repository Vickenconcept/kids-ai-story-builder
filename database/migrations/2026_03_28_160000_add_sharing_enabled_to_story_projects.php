<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_projects', function (Blueprint $table) {
            $table->boolean('sharing_enabled')->default(false)->after('cover_back');
        });
    }

    public function down(): void
    {
        Schema::table('story_projects', function (Blueprint $table) {
            $table->dropColumn('sharing_enabled');
        });
    }
};
