<?php

namespace App\Enums;

enum FeatureTier: string
{
    case Basic = 'basic';
    case Pro = 'pro';
    case Elite = 'elite';

    /** Returns true for any tier that includes Pro-level features (Pro and above). */
    public function isPro(): bool
    {
        return match ($this) {
            self::Pro, self::Elite => true,
            default => false,
        };
    }
}
