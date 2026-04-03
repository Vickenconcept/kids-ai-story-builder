<?php

namespace App\Services\Story\Video;

use App\Contracts\Story\PageVideoGenerator;
use App\Services\Media\StoryMediaStorage;
use App\Support\StoryMediaUrl;
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
        $audioBytes = null;
        $audioDurationSeconds = null;

        if (is_string($audioUrl) && $audioUrl !== '') {
            try {
                $audioBytes = Http::timeout(180)->get($audioUrl)->throw()->body();
                $audioDurationSeconds = $this->extractAudioDurationSeconds($audioBytes);
            } catch (\Throwable $e) {
                Log::warning('story.video.audio_prepare_failed', [
                    'error' => $e->getMessage(),
                ]);
                // Continue with fallback duration and no mux if audio cannot be prepared.
                $audioBytes = null;
                $audioDurationSeconds = null;
            }
        }

        $taskId = $this->createVideoTask($apiKey, $imageUrl, $pageText, $audioDurationSeconds);
        $videoUrl = $this->waitForVideoUrl($apiKey, $taskId);

        $videoBytes = Http::timeout(300)->get($videoUrl)->throw()->body();

        if (is_string($audioBytes) && $audioBytes !== '') {
            try {
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

    private function createVideoTask(string $apiKey, string $imageUrl, string $pageText, ?int $audioDurationSeconds): string
    {
        $defaultDuration = (int) config('services.runway.duration_seconds', 10);
        $minDuration = (int) config('services.runway.min_duration_seconds', 5);
        $maxDuration = (int) config('services.runway.max_duration_seconds', 20);
        $ratio = (string) config('services.runway.ratio', '1280:720');
        $model = (string) config('services.runway.model', 'gen4_turbo');
        $requestedDuration = $audioDurationSeconds ?? $defaultDuration;
        $duration = max($minDuration, min($maxDuration, $requestedDuration));

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

        return $taskId;
    }

    private function waitForVideoUrl(string $apiKey, string $taskId): string
    {
        $maxPolls = max(5, (int) config('services.runway.max_polls', 120));
        $sleepMs = max(500, (int) config('services.runway.poll_interval_ms', 2000));

        for ($i = 0; $i < $maxPolls; $i++) {
            $response = $this->runwayRequest($apiKey)
                ->get(self::BASE_URL.'/tasks/'.$taskId)
                ->throw()
                ->json();

            $status = strtoupper((string) ($response['status'] ?? ''));
            if (in_array($status, ['FAILED', 'CANCELLED'], true)) {
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

        $process = new Process([
            (string) config('services.ffmpeg.binary', 'ffmpeg'),
            '-y',
            '-i', $videoIn,
            '-i', $audioIn,
            '-c:v', 'copy',
            '-c:a', 'aac',
            '-shortest',
            $output,
        ]);

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

    private function extractAudioDurationSeconds(string $audioBytes): int
    {
        $tempDir = storage_path('app/tmp/runway-audio-probe-'.Str::uuid());

        if (! is_dir($tempDir) && ! mkdir($tempDir, 0775, true) && ! is_dir($tempDir)) {
            throw new RuntimeException('Unable to create temporary directory for audio duration probing.');
        }

        $audioIn = $tempDir.'/input.mp3';
        file_put_contents($audioIn, $audioBytes);

        $process = new Process([
            $this->ffprobeBinary(),
            '-v', 'error',
            '-show_entries', 'format=duration',
            '-of', 'default=noprint_wrappers=1:nokey=1',
            $audioIn,
        ]);

        $process->setTimeout(20);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->cleanupProbeTempDir($tempDir);
            throw new RuntimeException('FFprobe duration probe failed: '.trim($process->getErrorOutput()));
        }

        $seconds = (float) trim($process->getOutput());
        $this->cleanupProbeTempDir($tempDir);

        if ($seconds <= 0) {
            throw new RuntimeException('FFprobe returned an invalid audio duration.');
        }

        return max(1, (int) ceil($seconds));
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

    private function cleanupProbeTempDir(string $tempDir): void
    {
        $audioIn = $tempDir.'/input.mp3';
        if (is_file($audioIn)) {
            @unlink($audioIn);
        }

        if (is_dir($tempDir)) {
            @rmdir($tempDir);
        }
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
