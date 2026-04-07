<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPublicPageSeo
{
    /**
     * Set canonical URL and meta for public marketing pages (Inertia routes without a dedicated controller).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && app()->runningInConsole() === false) {
            $name = $request->route()?->getName();
            $appName = config('app.name', 'DreamForge AI');
            $absolute = url()->current();

            $meta = match ($name) {
                'home' => [
                    'title' => $appName.' — Create illustrated, narrated storybooks in minutes',
                    'description' => 'Turn one idea into a multi-page kids storybook with AI illustrations, read-aloud voice, and a shareable flipbook. No design skills required.',
                ],
                'sales' => [
                    'title' => $appName.' — AI storybooks with illustrations & narration',
                    'description' => 'Create illustrated children’s storybooks from a single prompt. Pages, matching art, narration, and a flipbook link you can share or sell.',
                ],
                'thank-you' => [
                    'title' => 'Thank you — '.$appName,
                    'description' => 'Your purchase is confirmed. Log in to DreamForge AI and start creating your first storybook.',
                ],
                'login' => [
                    'title' => 'Log in — '.$appName,
                    'description' => 'Sign in to DreamForge AI to create and manage your AI storybooks.',
                ],
                'register' => [
                    'title' => 'Create account — '.$appName,
                    'description' => 'Create your DreamForge AI account and start building illustrated storybooks in minutes.',
                ],
                'jv' => [
                    'title' => $appName.' — JV partner information',
                    'description' => 'Affiliate details, launch assets, and commission information for promoting DreamForge AI.',
                ],
                'oto1' => [
                    'title' => 'PRO Video upgrade — '.$appName,
                    'description' => 'Optional upgrade: turn story pages into video for YouTube, Shorts, and social platforms.',
                ],
                'oto2' => [
                    'title' => 'DFY story selling system — '.$appName,
                    'description' => 'Optional add-on: monetization guides, prompts, and publishing shortcuts for your storybooks.',
                ],
                default => null,
            };

            if (is_array($meta)) {
                seo()
                    ->title($meta['title'])
                    ->description($meta['description'])
                    ->url($absolute);
            }
        }

        return $next($request);
    }
}
