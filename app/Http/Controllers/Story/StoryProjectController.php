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
use App\Jobs\Story\GenerateStoryPageVideoJob;
use App\Models\StoryPage;
use App\Models\StoryProject;
use App\Services\Media\StoryMediaStorage;
use App\Services\Story\StoryPipelineDispatcher;
use App\Services\Story\StoryCreditService;
use App\Support\StoryMediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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
            && $user->feature_tier?->isPro();
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
            'feature_tier' => $request->user()->feature_tier?->value ?? FeatureTier::Basic->value,
            'video_credit_cost' => (int) config('story.credit_costs.video', 0),
        ]);
    }

    public function generatePageVideo(
        Request $request,
        StoryProject $story,
        StoryPage $page,
        StoryCreditService $credits,
    ): RedirectResponse {
        $this->authorize('update', $story);

        if ($page->story_project_id !== $story->id) {
            abort(404);
        }

        $user = $request->user();
        if (! $user->feature_tier?->isPro()) {
            return back()->with('error', 'Page video generation requires Pro tier or above.');
        }

        if (! filled($page->image_path)) {
            return back()->with('error', 'Generate image first before creating video for this page.');
        }

        $needed = $credits->cost('video');
        if ((int) $user->story_credits < $needed) {
            return back()->with('error', 'Not enough credits for page video. Required: '.$needed.' credits.');
        }

        GenerateStoryPageVideoJob::dispatch($page->id)
            ->onQueue(config('story.queues.video'));

        return back()->with('success', 'Video generation queued for page '.$page->page_number.'.');
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

        $isPro = $request->user()?->feature_tier?->isPro();
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

    public function exportKdpPackage(Request $request, StoryProject $story): BinaryFileResponse
    {
        $this->authorize('view', $story);

        $validated = $request->validate([
            'trim' => ['sometimes', Rule::in(['8.5x8.5', '8x10'])],
            'completed_only' => ['sometimes', 'boolean'],
            'include_pdf' => ['sometimes', 'boolean'],
        ]);

        $story->load('pages');
        $trim = (string) ($validated['trim'] ?? '8.5x8.5');
        $completedOnly = (bool) ($validated['completed_only'] ?? false);
        $includePdf = (bool) ($validated['include_pdf'] ?? true);

        $pages = $story->pages->sortBy('page_number')->values();
        if ($completedOnly) {
            $pages = $pages->filter(fn ($page) => $this->isPageCompleted($page))->values();
        }

        if ($pages->isEmpty()) {
            abort(422, 'No exportable pages found for the selected options.');
        }

        [$trimWidth, $trimHeight] = $this->trimDimensions($trim);

        $tmpPath = tempnam(sys_get_temp_dir(), 'kdp_');
        if ($tmpPath === false) {
            abort(500, 'Could not initialize export package.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpPath);
            abort(500, 'Could not create export archive.');
        }

        $imagePaths = [];
        $imageDataUris = [];
        $imageManifest = [];

        foreach ($pages as $page) {
            $img = $this->readBinaryFromStoredPath($page->image_path);

            if (! is_array($img)) {
                $imageManifest[] = [
                    'page_number' => $page->page_number,
                    'source_url' => StoryMediaUrl::resolve($page->image_path),
                    'included_path' => null,
                    'status' => 'missing',
                ];
                continue;
            }

            $ext = (string) ($img['ext'] ?? 'jpg');
            $mime = (string) ($img['mime'] ?? 'image/jpeg');
            $bytes = (string) ($img['bytes'] ?? '');
            $source = (string) ($img['source'] ?? StoryMediaUrl::resolve($page->image_path));

            $assetPath = sprintf('assets/images/page-%03d.%s', (int) $page->page_number, $ext);
            $zip->addFromString($assetPath, $bytes);
            $imagePaths[(string) $page->uuid] = $assetPath;
            $imageDataUris[(string) $page->uuid] = 'data:'.$mime.';base64,'.base64_encode($bytes);

            $imageManifest[] = [
                'page_number' => $page->page_number,
                'source_url' => $source,
                'included_path' => $assetPath,
                'status' => 'included',
            ];
        }

        $frontCoverAsset = $this->buildCoverAsset($story->cover_front);
        $backCoverAsset = $this->buildCoverAsset($story->cover_back);

        if (is_array($frontCoverAsset) && isset($frontCoverAsset['bytes'], $frontCoverAsset['ext'])) {
            $zip->addFromString('assets/covers/front-cover.'.$frontCoverAsset['ext'], (string) $frontCoverAsset['bytes']);
        }

        if (is_array($backCoverAsset) && isset($backCoverAsset['bytes'], $backCoverAsset['ext'])) {
            $zip->addFromString('assets/covers/back-cover.'.$backCoverAsset['ext'], (string) $backCoverAsset['bytes']);
        }

        $zip->addFromString('README-FIRST.md', implode("\n", [
            '# Amazon KDP Export Package',
            '',
            'This package was generated from your story project and includes manuscript files, metadata, image assets, and cover templates.',
            '',
            '## Export options used',
            '- Trim size: '.$trim,
            '- Include only completed pages: '.($completedOnly ? 'yes' : 'no'),
            '- Include print-ready PDFs: '.($includePdf ? 'yes' : 'no'),
            '',
            '## Included',
            '- manuscript/story.txt',
            '- manuscript/story.md',
            '- manuscript/kindle-manuscript.html',
            '- manuscript/paperback-manuscript-'.$trim.'.html',
            '- metadata/book.json',
            '- metadata/image-manifest.json',
            '- cover/kdp-cover-specs.json',
            '- cover/template-8.5x8.5.svg',
            '- cover/template-8x10.svg',
            '- print/paperback-interior-'.$trim.'.pdf (when PDF export is enabled)',
            '- print/cover-layout-'.$trim.'.pdf (when PDF export is enabled)',
            '',
            '## KDP Notes',
            '- Kindle eBook typically uses EPUB or DOCX.',
            '- Paperback typically uses print-ready PDF + full cover PDF.',
            '- Cover templates here follow KDP trim + bleed guidance and include a spine estimate.',
            '- Always proof manually before publishing.',
            '',
            '## Spine estimate used',
            '- White paper formula: page_count x 0.002252 inches',
        ]));

        $zip->addFromString('manuscript/story.txt', $this->buildKdpText($story, $pages));
        $zip->addFromString('manuscript/story.md', $this->buildKdpMarkdown($story, $pages, $imagePaths));
        $zip->addFromString('manuscript/kindle-manuscript.html', $this->buildKdpHtml($story, $pages, $imagePaths, false));
        $paperbackHtml = $this->buildKdpHtmlWithTrim($story, $pages, $imagePaths, true, $trimWidth, $trimHeight);
        $zip->addFromString('manuscript/paperback-manuscript-'.$trim.'.html', $paperbackHtml);

        if ($includePdf) {
            $paperbackPdfHtml = $this->buildKdpHtmlWithTrim($story, $pages, $imagePaths, true, $trimWidth, $trimHeight, $imageDataUris);
            $zip->addFromString(
                'print/paperback-interior-'.$trim.'.pdf',
                $this->renderPdf($paperbackPdfHtml, [
                    'paper' => [0, 0, $trimWidth * 72, $trimHeight * 72],
                    'orientation' => 'portrait',
                ]),
            );

            $coverPdfHtml = $this->buildCoverLayoutPdfHtml(
                $story,
                $trim,
                $trimWidth,
                $trimHeight,
                (int) max(1, $pages->count()),
                $frontCoverAsset,
                $backCoverAsset,
            );
            $cover = $this->kdpCoverSpecs((int) max(1, $pages->count()))['trim_options'][$trim] ?? null;
            if (is_array($cover)) {
                $zip->addFromString(
                    'print/cover-layout-'.$trim.'.pdf',
                    $this->renderPdf($coverPdfHtml, [
                        'paper' => [0, 0, ((float) ($cover['full_cover_width_in'] ?? 17.31)) * 72, ((float) ($cover['full_cover_height_in'] ?? 8.75)) * 72],
                        'orientation' => 'landscape',
                    ]),
                );
            }
        }

        $coverSpecs = $this->kdpCoverSpecs((int) max(1, $story->page_count));
        $zip->addFromString('cover/kdp-cover-specs.json', json_encode($coverSpecs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        $zip->addFromString('cover/template-8.5x8.5.svg', $this->buildCoverTemplateSvg(8.5, 8.5, (int) max(1, $story->page_count), $story->title));
        $zip->addFromString('cover/template-8x10.svg', $this->buildCoverTemplateSvg(8.0, 10.0, (int) max(1, $story->page_count), $story->title));

        $zip->addFromString('metadata/book.json', json_encode([
            'title' => $story->title,
            'topic' => $story->topic,
            'lesson_type' => $story->lesson_type,
            'age_group' => $story->age_group,
            'page_count' => $story->page_count,
            'exported_pages' => $pages->count(),
            'include_narration' => (bool) $story->include_narration,
            'include_video' => (bool) $story->include_video,
            'export_options' => [
                'trim' => $trim,
                'completed_only' => $completedOnly,
                'include_pdf' => $includePdf,
            ],
            'generated_at_utc' => now()->toIso8601String(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        $zip->addFromString('metadata/image-manifest.json', json_encode($imageManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]');

        $zip->close();

        $safeTitle = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($story->title)) ?: 'storybook';
        $filename = sprintf('kdp-export-%s-%s.zip', $safeTitle, now()->format('Ymd-His'));

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    private function buildKdpText(StoryProject $story, Collection $pages): string
    {
        $lines = [
            $story->title,
            str_repeat('=', max(3, mb_strlen($story->title))),
            '',
            'Topic: '.$story->topic,
            '',
        ];

        foreach ($pages as $page) {
            $lines[] = 'Page '.$page->page_number;
            $lines[] = trim((string) ($page->text_content ?? ''));
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function buildKdpMarkdown(StoryProject $story, Collection $pages, array $imagePaths): string
    {
        $out = '# '.$story->title."\n\n";
        $out .= '**Topic:** '.trim((string) $story->topic)."\n\n";

        foreach ($pages as $page) {
            $out .= '## Page '.(int) $page->page_number."\n\n";
            $text = trim((string) ($page->text_content ?? ''));
            if ($text !== '') {
                $out .= $text."\n\n";
            }

            $local = $imagePaths[(string) $page->uuid] ?? null;
            if (is_string($local) && $local !== '') {
                $out .= '![Page '.(int) $page->page_number.'](../'.$local.")\n\n";
            }
        }

        return $out;
    }

    private function buildKdpHtml(StoryProject $story, Collection $pages, array $imagePaths, bool $printLayout): string
    {
        return $this->buildKdpHtmlWithTrim($story, $pages, $imagePaths, $printLayout, 8.5, 8.5);
    }

    private function buildKdpHtmlWithTrim(
        StoryProject $story,
        Collection $pages,
        array $imagePaths,
        bool $printLayout,
        float $trimWidth,
        float $trimHeight,
        array $inlineImageData = [],
    ): string {
        $title = htmlspecialchars((string) $story->title, ENT_QUOTES, 'UTF-8');
        $topic = htmlspecialchars((string) $story->topic, ENT_QUOTES, 'UTF-8');

        $contentHeight = max(4.0, $trimHeight - 1.0);
        $imageMax = max(2.5, $trimHeight - 3.0);
        $css = $printLayout
            ? '@page { size: '.$trimWidth.'in '.$trimHeight.'in; margin: 0.5in; } body { margin: 0; font-family: Georgia, serif; color: #111; } .page { page-break-after: always; min-height: '.$contentHeight.'in; display: flex; flex-direction: column; gap: 0.35in; } .cover { page-break-after: always; min-height: '.$contentHeight.'in; display: grid; place-content: center; text-align: center; } img { width: 100%; max-height: '.$imageMax.'in; object-fit: contain; border: 1px solid #ddd; border-radius: 8px; } p { line-height: 1.45; font-size: 12pt; margin: 0; } h1 { margin: 0; font-size: 28pt; } h2 { margin: 0 0 0.2in; font-size: 16pt; }'
            : 'body { margin: 0 auto; max-width: 860px; padding: 24px; font-family: Georgia, serif; color: #111; } .page { margin-bottom: 32px; } img { width: 100%; max-height: 540px; object-fit: contain; border: 1px solid #ddd; border-radius: 8px; } p { line-height: 1.55; font-size: 1rem; } h1 { margin: 0; font-size: 2rem; } h2 { margin: 0 0 8px; font-size: 1.2rem; }';

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>'.$title.'</title><style>'.$css.'</style></head><body>';
        $html .= '<section class="cover"><h1>'.$title.'</h1><p>'.$topic.'</p></section>';

        foreach ($pages as $page) {
            $text = nl2br(htmlspecialchars((string) ($page->text_content ?? ''), ENT_QUOTES, 'UTF-8'));
            $imgSrc = $inlineImageData[(string) $page->uuid]
                ?? $imagePaths[(string) $page->uuid]
                ?? StoryMediaUrl::resolve($page->image_path);
            $imgHtml = is_string($imgSrc) && $imgSrc !== ''
                ? '<img src="'.htmlspecialchars((string) $imgSrc, ENT_QUOTES, 'UTF-8').'" alt="Page '.(int) $page->page_number.' illustration">'
                : '';

            $html .= '<section class="page">';
            $html .= '<h2>Page '.(int) $page->page_number.'</h2>';
            if ($imgHtml !== '') {
                $html .= $imgHtml;
            }
            $html .= '<p>'.$text.'</p>';
            $html .= '</section>';
        }

        return $html.'</body></html>';
    }

    private function renderPdf(string $html, array $paperOptions): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($paperOptions['paper'] ?? 'a4', $paperOptions['orientation'] ?? 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function buildCoverLayoutPdfHtml(
        StoryProject $story,
        string $trim,
        float $trimWidth,
        float $trimHeight,
        int $pageCount,
        ?array $frontCoverAsset = null,
        ?array $backCoverAsset = null,
    ): string
    {
        $cover = $this->kdpCoverSpecs($pageCount)['trim_options'][$trim] ?? null;
        if (! is_array($cover)) {
            return '<html><body><p>Invalid cover specs.</p></body></html>';
        }

        $fullW = (float) ($cover['full_cover_width_in'] ?? (($trimWidth * 2) + 0.3));
        $fullH = (float) ($cover['full_cover_height_in'] ?? ($trimHeight + 0.25));
        $bleed = (float) ($cover['bleed_in'] ?? 0.125);
        $spine = (float) ($cover['spine_in'] ?? 0.06);

        $title = htmlspecialchars((string) $story->title, ENT_QUOTES, 'UTF-8');
        $frontCss = $this->coverStyleCss($story->cover_front, $frontCoverAsset);
        $backCss = $this->coverStyleCss($story->cover_back, $backCoverAsset);

        return '<!doctype html><html><head><meta charset="utf-8"><style>'
            .'@page { size: '.$fullW.'in '.$fullH.'in; margin: 0; }'
            .'body { margin: 0; font-family: Arial, sans-serif; }'
            .'.sheet { width: '.$fullW.'in; height: '.$fullH.'in; position: relative; box-sizing: border-box; border: 1px solid #555; }'
            .'.trim { position: absolute; top: '.$bleed.'in; bottom: '.$bleed.'in; width: '.$trimWidth.'in; border: 2px dashed #2b5fbf; background: #f5f8ff; background-size: cover; background-position: center; }'
            .'.back { left: '.$bleed.'in; }'
            .'.front { right: '.$bleed.'in; }'
            .'.spine { position: absolute; top: '.$bleed.'in; bottom: '.$bleed.'in; width: '.$spine.'in; left: calc(50% - '.($spine / 2).'in); border: 2px solid #b48a00; background: #fff4cc; }'
            .'.label { position: absolute; font-size: 14px; color: #1f2d5c; }'
            .'.label.back { left: '.($bleed + 0.2).'in; top: '.($bleed + 0.2).'in; }'
            .'.label.front { right: '.($bleed + 0.2).'in; top: '.($bleed + 0.2).'in; }'
            .'.footer { position: absolute; left: '.($bleed + 0.2).'in; bottom: '.($bleed + 0.2).'in; font-size: 12px; color: #666; }'
            .'.front { '.$frontCss.' }'
            .'.back { '.$backCss.' }'
            .'</style></head><body><div class="sheet">'
            .'<div class="trim back"></div><div class="trim front"></div><div class="spine"></div>'
            .'<div class="label back">Back Cover '.$trim.'</div><div class="label front">Front Cover '.$trim.'</div>'
            .'<div class="footer">Title: '.$title.' | Spine: '.number_format($spine, 4).'in</div>'
            .'</div></body></html>';
    }

    private function buildCoverAsset(?array $cover): ?array
    {
        if (! is_array($cover)) {
            return null;
        }

        $kind = strtolower((string) ($cover['kind'] ?? ''));
        if (! in_array($kind, ['image', 'gif', 'ai_image'], true)) {
            return null;
        }

        $path = isset($cover['path']) && is_string($cover['path']) ? $cover['path'] : null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        return $this->readBinaryFromStoredPath($path);
    }

    private function coverStyleCss(?array $cover, ?array $asset): string
    {
        if (! is_array($cover)) {
            return 'background: #f5f8ff;';
        }

        $kind = strtolower((string) ($cover['kind'] ?? ''));
        if ($kind === 'solid' && isset($cover['color']) && is_string($cover['color'])) {
            return 'background: '.htmlspecialchars($cover['color'], ENT_QUOTES, 'UTF-8').';';
        }

        if ($kind === 'gradient') {
            $angle = is_numeric($cover['angle'] ?? null) ? (int) $cover['angle'] : 135;
            $from = is_string($cover['from'] ?? null) ? $cover['from'] : '#6366f1';
            $to = is_string($cover['to'] ?? null) ? $cover['to'] : '#ec4899';

            return 'background: linear-gradient('.$angle.'deg, '.htmlspecialchars($from, ENT_QUOTES, 'UTF-8').', '.htmlspecialchars($to, ENT_QUOTES, 'UTF-8').');';
        }

        if (is_array($asset) && isset($asset['bytes'], $asset['mime'])) {
            $uri = 'data:'.(string) $asset['mime'].';base64,'.base64_encode((string) $asset['bytes']);

            return 'background-image: url("'.$uri.'"); background-size: cover; background-position: center;';
        }

        return 'background: #f5f8ff;';
    }

    private function readBinaryFromStoredPath(?string $storedPath): ?array
    {
        if (! is_string($storedPath) || $storedPath === '') {
            return null;
        }

        if (str_starts_with($storedPath, 'http://') || str_starts_with($storedPath, 'https://')) {
            return $this->readBinaryFromUrl($storedPath);
        }

        try {
            $disk = Storage::disk('public');
            if ($disk->exists($storedPath)) {
                $bytes = (string) $disk->get($storedPath);
                $ext = strtolower((string) pathinfo($storedPath, PATHINFO_EXTENSION));
                $ext = $ext !== '' ? $ext : 'jpg';
                $mime = match ($ext) {
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                    'gif' => 'image/gif',
                    'svg' => 'image/svg+xml',
                    default => 'image/jpeg',
                };

                return [
                    'bytes' => $bytes,
                    'ext' => $ext,
                    'mime' => $mime,
                    'source' => StoryMediaUrl::resolve($storedPath),
                ];
            }
        } catch (\Throwable) {
            // fall through to URL attempt
        }

        $url = StoryMediaUrl::resolve($storedPath);
        if (! is_string($url) || $url === '') {
            return null;
        }

        return $this->readBinaryFromUrl($url);
    }

    private function readBinaryFromUrl(string $url): ?array
    {
        try {
            $resp = Http::timeout(30)->get($url);
            if (! $resp->successful()) {
                return null;
            }

            $contentType = strtolower((string) $resp->header('Content-Type', ''));
            $ext = match (true) {
                str_contains($contentType, 'png') => 'png',
                str_contains($contentType, 'webp') => 'webp',
                str_contains($contentType, 'gif') => 'gif',
                str_contains($contentType, 'svg') => 'svg',
                default => 'jpg',
            };
            $mime = $contentType !== '' ? $contentType : match ($ext) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'svg' => 'image/svg+xml',
                default => 'image/jpeg',
            };

            return [
                'bytes' => (string) $resp->body(),
                'ext' => $ext,
                'mime' => $mime,
                'source' => $url,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function trimDimensions(string $trim): array
    {
        return $trim === '8x10' ? [8.0, 10.0] : [8.5, 8.5];
    }

    private function isPageCompleted(object $page): bool
    {
        $hasText = trim((string) ($page->text_content ?? '')) !== '';
        $hasImage = filled($page->image_path ?? null);

        return $hasText && $hasImage;
    }

    private function kdpCoverSpecs(int $pageCount): array
    {
        $spine = round(max(0.06, $pageCount * 0.002252), 4);
        $bleed = 0.125;

        $mk = static function (float $trimW, float $trimH) use ($spine, $bleed): array {
            return [
                'trim_width_in' => $trimW,
                'trim_height_in' => $trimH,
                'bleed_in' => $bleed,
                'spine_in' => $spine,
                'full_cover_width_in' => round(($trimW * 2) + $spine + ($bleed * 2), 4),
                'full_cover_height_in' => round($trimH + ($bleed * 2), 4),
            ];
        };

        return [
            'page_count' => $pageCount,
            'paper_type' => 'white',
            'spine_formula' => 'page_count x 0.002252 inches',
            'trim_options' => [
                '8.5x8.5' => $mk(8.5, 8.5),
                '8x10' => $mk(8.0, 10.0),
            ],
        ];
    }

    private function buildCoverTemplateSvg(float $trimWidthIn, float $trimHeightIn, int $pageCount, string $title): string
    {
        $dpi = 300;
        $spineIn = max(0.06, $pageCount * 0.002252);
        $bleedIn = 0.125;
        $totalWidthIn = ($trimWidthIn * 2) + $spineIn + ($bleedIn * 2);
        $totalHeightIn = $trimHeightIn + ($bleedIn * 2);

        $w = (int) round($totalWidthIn * $dpi);
        $h = (int) round($totalHeightIn * $dpi);
        $bleedPx = (int) round($bleedIn * $dpi);
        $trimPx = (int) round($trimWidthIn * $dpi);
        $spinePx = (int) round($spineIn * $dpi);
                $contentHeightPx = $h - (2 * $bleedPx);
                $frontX = $w - $bleedPx - $trimPx;
                $spineX = (int) round(($w - $spinePx) / 2);
                $backLabelX = $bleedPx + 28;
                $frontLabelX = $frontX + 28;
                $labelY = $bleedPx + 42;
                $titleY = $h - $bleedPx - 24;
                $centerX = (int) round($w / 2);
                $centerY = (int) round($h / 2);
                $spineInLabel = number_format($spineIn, 4);

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
  <rect width="{$w}" height="{$h}" fill="#ffffff"/>
    <rect x="{$bleedPx}" y="{$bleedPx}" width="{$trimPx}" height="{$contentHeightPx}" fill="#f5f8ff" stroke="#5b74d6" stroke-width="2"/>
    <rect x="{$frontX}" y="{$bleedPx}" width="{$trimPx}" height="{$contentHeightPx}" fill="#f5f8ff" stroke="#5b74d6" stroke-width="2"/>
    <rect x="{$spineX}" y="{$bleedPx}" width="{$spinePx}" height="{$contentHeightPx}" fill="#fff2cc" stroke="#c9a227" stroke-width="2"/>
    <text x="{$backLabelX}" y="{$labelY}" font-size="28" font-family="Arial" fill="#1f2d5c">Back Cover ({$trimWidthIn}x{$trimHeightIn})</text>
        <text x="{$frontLabelX}" y="{$labelY}" font-size="28" font-family="Arial" fill="#1f2d5c">Front Cover ({$trimWidthIn}x{$trimHeightIn})</text>
    <text x="{$centerX}" y="{$centerY}" font-size="20" font-family="Arial" fill="#9a7d00" transform="rotate(90 {$centerX} {$centerY})">Spine {$spineInLabel}in</text>
    <text x="{$backLabelX}" y="{$titleY}" font-size="22" font-family="Arial" fill="#5a5a5a">Title: {$safeTitle}</text>
</svg>
SVG;
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
