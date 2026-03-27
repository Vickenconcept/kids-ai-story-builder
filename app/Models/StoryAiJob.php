<?php

namespace App\Models;

use App\Enums\StoryAiJobStatus;
use App\Models\Concerns\GeneratesUuid;
use App\Enums\StoryAiJobType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoryAiJob extends Model
{
    use GeneratesUuid;

    protected $table = 'story_ai_jobs';

    protected $fillable = [
        'story_project_id',
        'story_page_id',
        'type',
        'status',
        'payload',
        'error_message',
        'attempts',
        'started_at',
        'finished_at',
    ];

    public function casts(): array
    {
        return [
            'type' => StoryAiJobType::class,
            'status' => StoryAiJobStatus::class,
            'payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(StoryProject::class, 'story_project_id');
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(StoryPage::class, 'story_page_id');
    }
}
