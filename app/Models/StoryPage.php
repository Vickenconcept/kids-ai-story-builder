<?php

namespace App\Models;

use App\Models\Concerns\GeneratesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryPage extends Model
{
    use GeneratesUuid;

    protected $fillable = [
        'story_project_id',
        'page_number',
        'text_content',
        'quiz_questions',
        'image_path',
        'audio_path',
        'video_path',
        'asset_errors',
    ];

    public function casts(): array
    {
        return [
            'quiz_questions' => 'array',
            'asset_errors' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'story_project_id');
    }

    public function aiJobs(): HasMany
    {
        return $this->hasMany(StoryAiJob::class);
    }
}
