<?php

namespace App\Http\Middleware;

use App\Enums\FeatureTier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsElite
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->feature_tier !== FeatureTier::Elite) {
            abort(403);
        }

        return $next($request);
    }
}
