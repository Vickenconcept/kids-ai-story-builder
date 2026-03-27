<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStoryCreditsException extends RuntimeException
{
    public static function for(string $kind): self
    {
        return new self("Insufficient story credits for operation: {$kind}");
    }
}
