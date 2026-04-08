<?php

namespace App\Actions\Fortify;

use App\Mail\UserNotificationMail;
use App\Jobs\Email\SendCeoOnboardingEmailJob;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => 'required|string|confirmed|min:8',
            // 'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => bcrypt($input['password']),
        ]);

        try {
            Mail::to($user->email)->send(
                new UserNotificationMail(
                    subjectLine: 'Welcome to '.config('app.name', 'DreamForge AI'),
                    headline: 'Welcome aboard!',
                    lines: [
                        'Your account has been created successfully.',
                        'You can now start building your first AI storybook from your dashboard.',
                    ],
                    ctaLabel: 'Open Dashboard',
                    ctaUrl: url('/dashboard'),
                )
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send registration welcome email.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }

        SendCeoOnboardingEmailJob::dispatch($user->id, 1)->delay(now()->addHour());
        SendCeoOnboardingEmailJob::dispatch($user->id, 2)->delay(now()->addDay());
        SendCeoOnboardingEmailJob::dispatch($user->id, 3)->delay(now()->addDays(3));

        return $user;
    }
}
