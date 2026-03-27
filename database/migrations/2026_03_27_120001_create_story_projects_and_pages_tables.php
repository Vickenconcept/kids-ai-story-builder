<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('topic');
            $table->string('lesson_type')->default('moral');
            $table->string('age_group')->default('6-8');
            $table->unsignedTinyInteger('page_count')->default(5);
            $table->string('illustration_style')->default('cartoon');
            $table->boolean('include_quiz')->default(false);
            $table->boolean('include_narration')->default(true);
            $table->boolean('include_video')->default(false);
            $table->string('status')->default('draft');
            $table->unsignedSmallInteger('pages_completed')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('story_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('page_number');
            $table->text('text_content')->nullable();
            $table->json('quiz_questions')->nullable();
            $table->string('image_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('video_path')->nullable();
            $table->json('asset_errors')->nullable();
            $table->timestamps();

            $table->unique(['story_project_id', 'page_number']);
        });

        Schema::create('story_ai_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('story_page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_ai_jobs');
        Schema::dropIfExists('story_pages');
        Schema::dropIfExists('story_projects');
    }
};
