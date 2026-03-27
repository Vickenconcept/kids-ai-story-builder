<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

final class StoryMediaUrl
{
    /**
     * Resolve a stored story asset: full URL (e.g. Cloudinary) or legacy public-disk path.
     */
    public static function resolve(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        if (str_starts_with($stored, 'http://') || str_starts_with($stored, 'https://')) {
            return $stored;
        }

        return Storage::disk('public')->url($stored);
    }
}
