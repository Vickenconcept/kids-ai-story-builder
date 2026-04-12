<?php

namespace App\Http\Middleware;

use App\Enums\FeatureTier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasProTier
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tier = $user?->feature_tier;

        if (! $tier instanceof FeatureTier || ! $tier->isPro()) {
            abort(403);
        }

        return $next($request);
    }
}
