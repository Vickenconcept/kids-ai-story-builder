<?php

namespace App\Models;

use App\Enums\StoryProjectStatus;
use App\Models\Concerns\GeneratesUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoryProject extends Model
{
    use GeneratesUuid;

    protected $fillable = [
        'user_id',
        'title',
        'topic',
        'lesson_type',
        'age_group',
        'page_count',
        'illustration_style',
        'include_quiz',
        'include_narration',
        'include_video',
        'status',
        'pages_completed',
        'meta',
        'flip_gameplay_enabled',
        'cover_front',
        'cover_back',
        'sharing_enabled',
        'flip_settings',
    ];

    public function casts(): array
    {
        return [
            'include_quiz' => 'boolean',
            'include_narration' => 'boolean',
            'include_video' => 'boolean',
            'flip_gameplay_enabled' => 'boolean',
            'sharing_enabled' => 'boolean',
            'status' => StoryProjectStatus::class,
            'meta' => 'array',
            'cover_front' => 'array',
            'cover_back' => 'array',
            'flip_settings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(StoryPage::class)->orderBy('page_number');
    }

    public function aiJobs(): HasMany
    {
        return $this->hasMany(StoryAiJob::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
