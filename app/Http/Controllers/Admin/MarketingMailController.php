<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendMarketingMailRequest;
use App\Jobs\SendMarketingBroadcastEmailJob;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MarketingMailController extends Controller
{
    private const MAX_RECIPIENTS = 500;

    public function index(Request $request): Response
    {
        $this->authorize('manage-users', $request->user());

        return Inertia::render('Admin/MarketingMail/Index', [
            'maxRecipients' => self::MAX_RECIPIENTS,
        ]);
    }

    public function userSearch(Request $request): JsonResponse
    {
        $this->authorize('manage-users', $request->user());

        $q = trim((string) $request->query('q', ''));

        $users = User::query()
            ->when($q !== '', function ($query) use ($q): void {
                $query->where(function ($inner) use ($q): void {
                    $inner->where('email', 'like', '%'.$q.'%')
                        ->orWhere('name', 'like', '%'.$q.'%');
                });
            })
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
            ]);

        return response()->json(['users' => $users]);
    }

    public function send(SendMarketingMailRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $subject = (string) $validated['subject'];
        $bodyHtml = $this->sanitizeMarketingHtml((string) $validated['body_html']);
        $userIds = array_values(array_unique(array_map('intval', $validated['user_ids'] ?? [])));

        $fromUsers = [];
        if ($userIds !== []) {
            $fromUsers = User::query()->whereIn('id', $userIds)->pluck('email')->map(fn ($e) => strtolower((string) $e))->all();
        }

        $extraRaw = trim((string) ($validated['extra_emails'] ?? ''));
        $fromPaste = [];
        if ($extraRaw !== '') {
            foreach (explode(',', $extraRaw) as $part) {
                $e = strtolower(trim($part));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                    $fromPaste[] = $e;
                }
            }
        }

        $recipients = array_values(array_unique(array_merge($fromUsers, $fromPaste)));

        if ($recipients === []) {
            return back()->with('error', 'No valid recipient email addresses.');
        }

        if (count($recipients) > self::MAX_RECIPIENTS) {
            return back()->with('error', 'Too many recipients. Maximum is '.self::MAX_RECIPIENTS.' per send.');
        }

        foreach ($recipients as $email) {
            SendMarketingBroadcastEmailJob::dispatch($email, $subject, $bodyHtml);
        }

        return back()->with('success', count($recipients).' email(s) queued for delivery.');
    }

    private function sanitizeMarketingHtml(string $html): string
    {
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? '';
        $html = preg_replace('#<iframe\b[^>]*>.*?</iframe>#is', '', $html) ?? '';
        $html = preg_replace('#\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? '';

        return $html;
    }
}
