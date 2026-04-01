<?php

namespace App\Http\Controllers\Story;

use App\Contracts\Story\PageImageGenerator;
use App\Data\Story\PageImageInput;
use App\Enums\FeatureTier;
use App\Enums\StoryAiJobStatus;
use App\Enums\StoryProjectStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStoryProjectRequest;
use App\Http\Requests\UpdateStoryProjectPresentationRequest;
use App\Models\StoryProject;
use App\Services\Media\StoryMediaStorage;
use App\Services\Story\StoryPipelineDispatcher;
use App\Services\Story\StoryCreditService;
use App\Support\StoryMediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StoryProjectController extends Controller
{
    /**
     * Delete a single story project.
     */
    public function destroy(Request $request, StoryProject $story): RedirectResponse
    {
        $this->authorize('delete', $story);
        $story->delete();
        return redirect()->route('stories.index')->with('success', 'Story deleted.');
    }

    /**
     * Bulk delete story projects.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return redirect()->route('stories.index')->with('error', 'No stories selected.');
        }
        $userId = $request->user()->id;
        $deleted = StoryProject::where('user_id', $userId)->whereIn('id', $ids)->delete();
        return redirect()->route('stories.index')->with('success', "$deleted stories deleted.");
    }
    public function index(Request $request): Response
    {
        $projects = StoryProject::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get([
                'id',
                'uuid',
                'title',
                'topic',
                'status',
                'page_count',
                'pages_completed',
                'created_at',
                'updated_at',
            ]);

        return Inertia::render('Stories/Index', [
            'projects' => $projects,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', StoryProject::class);

        $user = $request->user();

        return Inertia::render('Stories/Create', [
            'featureTier' => $user->feature_tier?->value ?? FeatureTier::Basic->value,
            'storyCredits' => (int) $user->story_credits,
            'creditCosts' => [
                'text' => (int) config('story.credit_costs.text', 0),
                'image' => (int) config('story.credit_costs.image', 0),
                'audio' => (int) config('story.credit_costs.audio', 0),
                'video' => (int) config('story.credit_costs.video', 0),
            ],
        ]);
    }

    public function store(
        StoreStoryProjectRequest $request,
        StoryPipelineDispatcher $dispatcher,
        StoryCreditService $credits,
    ): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('create', StoryProject::class);

        $user = $request->user();
        $includeVideo = $request->boolean('include_video')
            && $user->feature_tier === FeatureTier::Pro;
        $includeNarration = $request->boolean('include_narration');
        $pageCount = $request->integer('page_count');

        $estimate = $credits->estimateForProject($pageCount, true, $includeNarration, $includeVideo, includeText: true);
        if ((int) $user->story_credits < $estimate['total']) {
            throw ValidationException::withMessages([
                'page_count' => 'Not enough credits for this setup. Required: '.$estimate['total'].' credits.',
            ]);
        }

        $project = StoryProject::query()->create([
            'user_id' => $user->id,
            'title' => $request->string('title')->toString(),
            'topic' => $request->string('topic')->toString(),
            'lesson_type' => $request->string('lesson_type')->toString(),
            'age_group' => $request->string('age_group')->toString(),
            'page_count' => $pageCount,
            'illustration_style' => $request->string('illustration_style')->toString(),
            'include_quiz' => $request->boolean('include_quiz'),
            'include_narration' => $includeNarration,
            'include_video' => $includeVideo,
            'status' => StoryProjectStatus::Processing,
            'pages_completed' => 0,
        ]);

        $dispatcher->queueStoryText($project);

        return redirect()->route('stories.show', $project);
    }

    public function show(Request $request, StoryProject $story): Response
    {
        $this->authorize('view', $story);

        $story->load('pages');

        $jobCounts = $story->aiJobs()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $latestFailedError = $story->aiJobs()
            ->where('status', StoryAiJobStatus::Failed->value)
            ->latest('id')
            ->value('error_message');

        $pages = $story->pages->map(fn ($page) => [
            'id' => $page->id,
            'uuid' => $page->uuid,
            'page_number' => $page->page_number,
            'text_content' => $page->text_content,
            'quiz_questions' => $page->quiz_questions,
            'asset_errors' => $page->asset_errors,
            'image_url' => StoryMediaUrl::resolve($page->image_path),
            'audio_url' => StoryMediaUrl::resolve($page->audio_path),
            'video_url' => StoryMediaUrl::resolve($page->video_path),
        ]);

        return Inertia::render('Stories/Show', [
            'project' => [
                'id' => $story->id,
                'uuid' => $story->uuid,
                'title' => $story->title,
                'topic' => $story->topic,
                'status' => $story->status->value,
                'page_count' => $story->page_count,
                'pages_completed' => $story->pages_completed,
                'include_quiz' => $story->include_quiz,
                'include_narration' => $story->include_narration,
                'include_video' => $story->include_video,
                'illustration_style' => $story->illustration_style,
                'tts_voice' => is_array($story->meta) ? ($story->meta['tts_voice'] ?? config('story.models.tts_voice')) : config('story.models.tts_voice'),
                'flip_gameplay_enabled' => $story->flip_gameplay_enabled,
                'cover_front' => $this->hydrateCover($story->cover_front),
                'cover_back' => $this->hydrateCover($story->cover_back),
                'sharing_enabled' => $story->sharing_enabled,
                'public_read_url' => route('stories.public.show', $story, absolute: true),
                'flip_settings' => $story->flip_settings,
                'queue' => [
                    'total' => (int) $jobCounts->sum(),
                    'pending' => (int) ($jobCounts[StoryAiJobStatus::Pending->value] ?? 0),
                    'running' => (int) ($jobCounts[StoryAiJobStatus::Running->value] ?? 0),
                    'succeeded' => (int) ($jobCounts[StoryAiJobStatus::Succeeded->value] ?? 0),
                    'failed' => (int) ($jobCounts[StoryAiJobStatus::Failed->value] ?? 0),
                    'last_error' => $latestFailedError,
                ],
                'can_start_media' => $story->status === StoryProjectStatus::Draft,
            ],
            'pages' => $pages,
            'story_credits' => $request->user()->story_credits,
        ]);
    }

    public function startMediaGeneration(
        Request $request,
        StoryProject $story,
        StoryPipelineDispatcher $dispatcher,
        StoryCreditService $credits,
    ): RedirectResponse
    {
        $this->authorize('update', $story);

        $validated = $request->validate([
            'generate_images' => ['sometimes', 'boolean'],
            'generate_audio' => ['sometimes', 'boolean'],
            'generate_video' => ['sometimes', 'boolean'],
        ]);

        $story->loadMissing('pages');

        if ($story->status !== StoryProjectStatus::Draft) {
            return back();
        }

        if ($story->pages->isEmpty()) {
            return back()->with('error', 'No generated pages found. Please generate story text first.');
        }

        $generateImages = (bool) ($validated['generate_images'] ?? true);
        $generateAudio = (bool) ($validated['generate_audio'] ?? $story->include_narration);
        $generateVideo = (bool) ($validated['generate_video'] ?? $story->include_video);

        if ($generateVideo) {
            $generateImages = true;
        }

        $isPro = $request->user()?->feature_tier === FeatureTier::Pro;
        if (! $isPro) {
            $generateVideo = false;
        }

        $estimate = $credits->estimateForProject(
            $story->page_count,
            $generateImages,
            $generateAudio,
            $generateVideo,
            includeText: false,
        );

        if ((int) $request->user()->story_credits < $estimate['total']) {
            return back()->with('error', 'Not enough credits to start selected media. Required: '.$estimate['total'].' credits.');
        }

        $story->update([
            'status' => StoryProjectStatus::Processing,
            'pages_completed' => 0,
        ]);

        $dispatcher->dispatchSelectedMedia(
            $story->fresh(['pages', 'user']),
            $generateImages,
            $generateAudio,
            $generateVideo,
        );

        return back();
    }

    public function updatePresentation(UpdateStoryProjectPresentationRequest $request, StoryProject $story): RedirectResponse
    {
        $this->authorize('update', $story);

        $validated = $request->validated();
        if (array_key_exists('meta', $validated) && is_array($validated['meta'])) {
            $validated['meta'] = array_merge($story->meta ?? [], $validated['meta']);
        }

        $story->update($validated);

        return back();
    }

    public function uploadCover(Request $request, StoryProject $story, StoryMediaStorage $storage): RedirectResponse
    {
        $this->authorize('update', $story);

        $validated = $request->validate([
            'surface' => ['required', Rule::in(['front', 'back'])],
            'file' => ['required', 'file', 'max:10240', 'mimes:jpeg,jpg,png,gif,webp'],
        ]);

        $uploaded = $request->file('file');
        $ext = strtolower($uploaded->getClientOriginalExtension() ?: 'png');
        $name = 'cover-'.uniqid('', true).'.'.$ext;
        $dir = 'stories/'.$story->id.'/covers';
        $stored = $storage->storeBytes($uploaded->getContent(), $dir, $name, 'auto');
        $kind = $ext === 'gif' ? 'gif' : 'image';
        $config = [
            'kind' => $kind,
            'path' => $stored,
            'frame' => $this->inheritedCoverFrame($validated['surface'] === 'front' ? $story->cover_front : $story->cover_back),
        ];

        if ($validated['surface'] === 'front') {
            $story->update(['cover_front' => $config]);
        } else {
            $story->update(['cover_back' => $config]);
        }

        return back();
    }

    public function generateCoverAi(Request $request, StoryProject $story, PageImageGenerator $generator): RedirectResponse
    {
        $this->authorize('update', $story);

        $validated = $request->validate([
            'surface' => ['required', Rule::in(['front', 'back'])],
            'prompt' => ['nullable', 'string', 'max:2000'],
        ]);

        $promptBase = $validated['prompt'] ?? ($story->title.'. '.$story->topic);
        $pageText = 'Book cover illustration, eye-catching, title mood: '.$promptBase;

        $input = new PageImageInput(
            $story->title,
            $pageText,
            $story->illustration_style,
            $story->age_group,
        );

        $dir = 'stories/'.$story->id.'/covers';
        $path = $generator->generate($input, $dir);
        $config = [
            'kind' => 'ai_image',
            'path' => $path,
            'prompt' => $promptBase,
            'frame' => $this->inheritedCoverFrame($validated['surface'] === 'front' ? $story->cover_front : $story->cover_back),
        ];

        if ($validated['surface'] === 'front') {
            $story->update(['cover_front' => $config]);
        } else {
            $story->update(['cover_back' => $config]);
        }

        return back();
    }

    /**
     * @param  array<string, mixed>|null  $config
     * @return array<string, mixed>|null
     */
    private function hydrateCover(?array $config): ?array
    {
        if ($config === null || $config === []) {
            return null;
        }

        $out = $config;
        $kind = $config['kind'] ?? '';

        if (in_array($kind, ['image', 'gif', 'ai_image'], true) && ! empty($config['path']) && is_string($config['path'])) {
            $out['url'] = StoryMediaUrl::resolve($config['path']);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>|null  $previous
     */
    private function inheritedCoverFrame(?array $previous): string
    {
        $f = is_array($previous) && isset($previous['frame']) && is_string($previous['frame'])
            ? $previous['frame']
            : 'classic-leather';

        return in_array($f, UpdateStoryProjectPresentationRequest::COVER_FRAME_IDS, true)
            ? $f
            : 'classic-leather';
    }
}
