<?php

namespace App\Services\Story\Video;

use App\Contracts\Story\PageVideoGenerator;
use App\Services\Media\StoryMediaStorage;
use App\Support\StoryMediaUrl;
use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class RunwayPageVideoGenerator implements PageVideoGenerator
{
    private const BASE_URL = 'https://api.dev.runwayml.com/v1';

    public function __construct(
        private readonly StoryMediaStorage $media,
    ) {}

    public function generate(
        string $pageText,
        ?string $relativeImagePath,
        ?string $relativeAudioPath,
        string $storageDirectory,
        ?string $resumeRunwayTaskId = null,
        ?Closure $onRunwayTaskIdChanged = null,
    ): string {
        $apiKey = trim((string) config('services.runway.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('RUNWAY_API_KEY is required for video generation.');
        }

        $imageUrl = StoryMediaUrl::resolve($relativeImagePath);
        if (! is_string($imageUrl) || $imageUrl === '') {
            throw new RuntimeException('Cannot generate video without a page image URL.');
        }

        $audioUrl = StoryMediaUrl::resolve($relativeAudioPath);
        $taskId = $this->resolveOrCreateRunwayTask(
            $apiKey,
            $imageUrl,
            $pageText,
            $resumeRunwayTaskId,
            $onRunwayTaskIdChanged,
        );
        $videoUrl = $this->waitForVideoUrl($apiKey, $taskId, $onRunwayTaskIdChanged);

        $videoBytes = Http::timeout(300)->get($videoUrl)->throw()->body();

        if (is_string($audioUrl) && $audioUrl !== '') {
            try {
                $audioBytes = Http::timeout(180)->get($audioUrl)->throw()->body();
                $videoBytes = $this->muxAudio($videoBytes, $audioBytes);
            } catch (\Throwable $e) {
                Log::warning('story.video.mux_audio_failed', [
                    'error' => $e->getMessage(),
                ]);
                // Keep generated video even if optional audio muxing fails.
            }
        }

        $name = 'video-'.Str::uuid().'.mp4';

        return $this->media->storeBytes($videoBytes, $storageDirectory, $name, 'video');
    }

    private function resolveOrCreateRunwayTask(
        string $apiKey,
        string $imageUrl,
        string $pageText,
        ?string $resumeRunwayTaskId,
        ?Closure $onRunwayTaskIdChanged,
    ): string {
        $resume = $resumeRunwayTaskId !== null ? trim($resumeRunwayTaskId) : '';
        if ($resume !== '') {
            return $resume;
        }

        $taskId = $this->createVideoTask($apiKey, $imageUrl, $pageText);
        if ($onRunwayTaskIdChanged) {
            $onRunwayTaskIdChanged($taskId);
        }

        return $taskId;
    }

    private function createVideoTask(string $apiKey, string $imageUrl, string $pageText): string
    {
        $defaultDuration = (int) config('services.runway.duration_seconds', 10);
        $minDuration = (int) config('services.runway.min_duration_seconds', 5);
        $maxDuration = (int) config('services.runway.max_duration_seconds', 10);
        $ratio = (string) config('services.runway.ratio', '1280:720');
        $model = (string) config('services.runway.model', 'gen4_turbo');
        $duration = max($minDuration, min($maxDuration, $defaultDuration));

        $payload = [
            'model' => $model,
            'promptImage' => $imageUrl,
            'promptText' => mb_substr(trim($pageText), 0, 400),
            'duration' => $duration,
            'ratio' => $ratio,
        ];

        $response = $this->runwayRequest($apiKey)
            ->post(self::BASE_URL.'/image_to_video', $payload)
            ->throw()
            ->json();

        $taskId = (string) ($response['id'] ?? '');
        if ($taskId === '') {
            throw new RuntimeException('Runway did not return a task ID.');
        }

        Log::info('story.video.runway.task_created', [
            'task_id' => $taskId,
            'duration' => $duration,
            'model' => $model,
        ]);

        return $taskId;
    }

    private function waitForVideoUrl(string $apiKey, string $taskId, ?Closure $onRunwayTaskIdChanged): string
    {
        $maxPolls = max(5, (int) config('services.runway.max_polls', 120));
        $sleepMs = max(500, (int) config('services.runway.poll_interval_ms', 2000));

        for ($i = 0; $i < $maxPolls; $i++) {
            $httpResponse = $this->runwayRequest($apiKey)
                ->get(self::BASE_URL.'/tasks/'.$taskId);

            if ($httpResponse->status() === 404) {
                if ($onRunwayTaskIdChanged) {
                    $onRunwayTaskIdChanged(null);
                }
                throw new RuntimeException('Runway task not found; stale task id was cleared.');
            }

            $httpResponse->throw();
            /** @var array<string, mixed> $response */
            $response = $httpResponse->json();

            $status = strtoupper((string) ($response['status'] ?? ''));
            if ($i === 0 || ($i + 1) % 15 === 0) {
                Log::info('story.video.runway.poll', [
                    'task_id' => $taskId,
                    'poll' => $i + 1,
                    'max_polls' => $maxPolls,
                    'status' => $status,
                ]);
            }
            if (in_array($status, ['FAILED', 'CANCELLED'], true)) {
                if ($onRunwayTaskIdChanged) {
                    $onRunwayTaskIdChanged(null);
                }
                $reason = (string) ($response['error'] ?? $response['failure'] ?? 'Unknown Runway failure.');
                throw new RuntimeException('Runway video task failed: '.$reason);
            }

            if (in_array($status, ['SUCCEEDED', 'COMPLETED'], true)) {
                $url = $this->extractVideoUrl($response);
                if ($url !== '') {
                    return $url;
                }

                throw new RuntimeException('Runway task completed without a downloadable video URL.');
            }

            usleep($sleepMs * 1000);
        }

        throw new RuntimeException('Runway video task timed out before completion.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractVideoUrl(array $payload): string
    {
        $candidates = [
            data_get($payload, 'output.0'),
            data_get($payload, 'output.video.0'),
            data_get($payload, 'output.video'),
            data_get($payload, 'artifacts.0.url'),
            data_get($payload, 'url'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && str_starts_with($candidate, 'http')) {
                return $candidate;
            }
        }

        return '';
    }

    private function runwayRequest(string $apiKey): \Illuminate\Http\Client\PendingRequest
    {
        $version = (string) config('services.runway.version', '2024-11-06');

        return Http::timeout(120)
            ->acceptJson()
            ->withToken($apiKey)
            ->withHeaders([
                'X-Runway-Version' => $version,
            ]);
    }

    private function muxAudio(string $videoBytes, string $audioBytes): string
    {
        $tempDir = storage_path('app/tmp/runway-video-'.Str::uuid());

        if (! is_dir($tempDir) && ! mkdir($tempDir, 0775, true) && ! is_dir($tempDir)) {
            throw new RuntimeException('Unable to create temporary directory for video muxing.');
        }

        $videoIn = $tempDir.'/input.mp4';
        $audioIn = $tempDir.'/input.mp3';
        $output = $tempDir.'/output.mp4';

        file_put_contents($videoIn, $videoBytes);
        file_put_contents($audioIn, $audioBytes);

        $videoDuration = $this->probeDurationSeconds($videoIn);
        $audioDuration = $this->probeDurationSeconds($audioIn);
        $loopCount = 0;

        if ($videoDuration > 0.0 && $audioDuration > $videoDuration) {
            // stream_loop value is "additional repeats", so 1 means play twice total.
            $loopCount = max(0, (int) ceil($audioDuration / $videoDuration) - 1);
        }

        $command = [
            (string) config('services.ffmpeg.binary', 'ffmpeg'),
            '-y',
        ];

        if ($loopCount > 0) {
            $command[] = '-stream_loop';
            $command[] = (string) $loopCount;
        }

        $command[] = '-i';
        $command[] = $videoIn;
        $command[] = '-i';
        $command[] = $audioIn;

        if ($loopCount > 0) {
            // Re-encode when looping to avoid container/timestamp issues on concatenated repeats.
            $command[] = '-c:v';
            $command[] = 'libx264';
            $command[] = '-pix_fmt';
            $command[] = 'yuv420p';
        } else {
            $command[] = '-c:v';
            $command[] = 'copy';
        }

        $command[] = '-c:a';
        $command[] = 'aac';
        $command[] = '-shortest';
        $command[] = $output;

        $process = new Process($command);

        $process->setTimeout(180);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($output)) {
            $this->cleanupTempDir($tempDir);
            throw new RuntimeException('FFmpeg audio muxing failed: '.trim($process->getErrorOutput()));
        }

        $bytes = (string) file_get_contents($output);
        $this->cleanupTempDir($tempDir);

        return $bytes;
    }

    private function probeDurationSeconds(string $inputPath): float
    {
        $process = new Process([
            $this->ffprobeBinary(),
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $inputPath,
        ]);

        $process->setTimeout(20);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('FFprobe duration probe failed: '.trim($process->getErrorOutput()));
        }

        $seconds = (float) trim((string) $process->getOutput());
        if ($seconds <= 0) {
            throw new RuntimeException('FFprobe returned an invalid media duration.');
        }

        return $seconds;
    }

    private function ffprobeBinary(): string
    {
        $ffmpeg = (string) config('services.ffmpeg.binary', 'ffmpeg');
        if ($ffmpeg === '') {
            return 'ffprobe';
        }

        if (preg_match('/ffmpeg(\.exe)?$/i', $ffmpeg) === 1) {
            return (string) preg_replace('/ffmpeg(\.exe)?$/i', 'ffprobe$1', $ffmpeg);
        }

        return 'ffprobe';
    }

    private function cleanupTempDir(string $tempDir): void
    {
        foreach (['/input.mp4', '/input.mp3', '/output.mp4'] as $file) {
            $path = $tempDir.$file;
            if (is_file($path)) {
                @unlink($path);
            }
        }

        if (is_dir($tempDir)) {
            @rmdir($tempDir);
        }
    }
}
