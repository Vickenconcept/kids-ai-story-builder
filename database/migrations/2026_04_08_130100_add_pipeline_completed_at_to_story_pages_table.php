<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('story_pages', function (Blueprint $table): void {
            $table->timestamp('pipeline_completed_at')->nullable()->after('video_path');
            $table->index(['story_project_id', 'pipeline_completed_at'], 'story_pages_project_pipeline_completed_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('story_pages', function (Blueprint $table): void {
            $table->dropIndex('story_pages_project_pipeline_completed_idx');
            $table->dropColumn('pipeline_completed_at');
        });
    }
};
