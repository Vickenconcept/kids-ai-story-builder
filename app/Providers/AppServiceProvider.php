<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        $storyAdminGate = function ($user): bool {
            $adminEmails = collect((array) config('story.admin_emails', []))
                ->map(fn ($email) => strtolower(trim((string) $email)))
                ->filter()
                ->values()
                ->all();

            return in_array(strtolower((string) $user->email), $adminEmails, true);
        };

        Gate::define('manage-credit-packs', $storyAdminGate);
        Gate::define('manage-plans', $storyAdminGate);
        Gate::define('manage-users', $storyAdminGate);

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
