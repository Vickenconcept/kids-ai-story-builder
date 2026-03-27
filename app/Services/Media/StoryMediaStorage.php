<?php

namespace App\Services\Media;

use Cloudinary\Cloudinary;
use Illuminate\Support\Facades\Storage;

/**
 * Persists generated or uploaded story assets to Cloudinary when configured,
 * otherwise to the public local disk (relative path).
 */
final class StoryMediaStorage
{
    private ?Cloudinary $cloudinary;

    public function __construct()
    {
        $c = config('services.cloudinary', []);
        $url = isset($c['url']) && is_string($c['url']) ? trim($c['url']) : '';
        if (str_starts_with($url, 'CLOUDINARY_URL=')) {
            $url = trim(substr($url, strlen('CLOUDINARY_URL=')));
        }

        if ($url !== '' && str_starts_with($url, 'cloudinary://')) {
            $this->cloudinary = new Cloudinary($url);

            return;
        }

        $name = $c['cloud_name'] ?? '';
        $key = $c['api_key'] ?? '';
        $secret = $c['api_secret'] ?? '';

        if (is_string($name) && $name !== '' && is_string($key) && $key !== '' && is_string($secret) && $secret !== '') {
            $this->cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $name,
                    'api_key' => $key,
                    'api_secret' => $secret,
                ],
            ]);

            return;
        }

        $this->cloudinary = null;
    }

    public function usesCloudinary(): bool
    {
        return $this->cloudinary !== null;
    }

    /**
     * @param  string  $directory  Logical folder, e.g. stories/5
     * @param  string  $filename  File name with extension (used for MIME + local path)
     * @param  string  $resourceType  Cloudinary resource_type: auto|image|video|raw
     * @return string Secure URL (Cloudinary) or relative path on public disk
     */
    public function storeBytes(string $bytes, string $directory, string $filename, string $resourceType = 'auto'): string
    {
        if ($this->cloudinary !== null) {
            $root = trim((string) config('services.cloudinary.folder', 'ai-story-book'), '/');
            $folder = $root.'/'.trim($directory, '/');
            $publicId = pathinfo($filename, PATHINFO_FILENAME);
            $mime = $this->mimeForFilename($filename);
            $dataUri = 'data:'.$mime.';base64,'.base64_encode($bytes);
            $type = $resourceType === 'auto' ? 'auto' : $resourceType;
            $result = $this->cloudinary->uploadApi()->upload($dataUri, [
                'folder' => $folder,
                'public_id' => $publicId,
                'resource_type' => $type,
            ]);

            return (string) $result['secure_url'];
        }

        $path = trim($directory, '/').'/'.$filename;
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    private function mimeForFilename(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'json' => 'application/json',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
