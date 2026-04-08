<?php

namespace App\Jobs\Email;

use App\Mail\UserNotificationMail;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendCeoOnboardingEmailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $userId,
        public int $sequenceStep,
    ) {}

    public function handle(): void
    {
        if (! (bool) config('mail.onboarding.enabled', true)) {
            return;
        }

        $user = User::query()->find($this->userId);
        if (! $user) {
            return;
        }

        $message = $this->buildMessage($user->name ?: 'there');

        if (! $message) {
            return;
        }

        try {
            Mail::to($user->email)->send(new UserNotificationMail(
                subjectLine: $message['subject'],
                headline: $message['headline'],
                lines: $message['lines'],
                ctaLabel: 'Open Your Dashboard',
                ctaUrl: url('/dashboard'),
                fromName: (string) config('mail.onboarding.ceo_name', 'William Bicto'),
                fromAddress: (string) config('mail.onboarding.from_address', config('mail.from.address')),
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed sending CEO onboarding email.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'sequence_step' => $this->sequenceStep,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{subject:string,headline:string,lines:array<int,string>}|null
     */
    private function buildMessage(string $name): ?array
    {
        return match ($this->sequenceStep) {
            1 => [
                'subject' => "Welcome to the family, {$name}",
                'headline' => "Hey {$name}, welcome to our community",
                'lines' => [
                    'I just wanted to personally welcome you.',
                    'You are not just another account here. You matter to us, and we are genuinely excited to have you with us.',
                    'Take your time, explore, and remember: every great journey starts small.',
                    'I am rooting for you.',
                    '— William Bicto',
                ],
            ],
            2 => [
                'subject' => 'A quick personal note from William',
                'headline' => "You're not doing this alone",
                'lines' => [
                    'Most people quietly wonder if they can really do this. That feeling is normal.',
                    'What matters is showing up consistently, even when progress feels slow.',
                    'This community is built for people like you: creators who want to grow, learn, and keep going.',
                    'I see you. Keep moving forward.',
                    '— William Bicto',
                ],
            ],
            3 => [
                'subject' => 'You belong here',
                'headline' => 'One last welcome message from me',
                'lines' => [
                    'I want you to remember this: your story and voice are valuable.',
                    'You are now part of a community that believes in creativity, momentum, and helping each other win.',
                    'If you stay consistent, your confidence will catch up with your vision.',
                    'Thank you for trusting us. We are honored to have you here.',
                    '— William Bicto',
                ],
            ],
            default => null,
        };
    }
}

