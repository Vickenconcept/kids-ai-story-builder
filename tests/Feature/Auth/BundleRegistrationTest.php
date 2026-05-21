<?php

namespace Tests\Feature\Auth;

use App\Enums\FeatureTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class BundleRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyFeature(Features::registration());
    }

    public function test_bundle_registration_screen_renders_for_pro_access(): void
    {
        $response = $this->get(route('bundle.register', ['access' => 'pro']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('auth/BundleRegister')
            ->where('access', 'pro')
            ->where('accessLabel', 'Pro'));
    }

    public function test_invalid_access_redirects_to_basic_bundle_register(): void
    {
        $response = $this->get(route('bundle.register', ['access' => 'invalid']));

        $response->assertRedirect(route('bundle.register', ['access' => FeatureTier::Basic->value]));
    }

    public function test_new_users_can_register_with_bundle_tier_from_session(): void
    {
        $this->withSession(['bundle_register.tier' => 'elite'])
            ->post(route('bundle.register.store'), [
                'name' => 'Bundle User',
                'email' => 'bundle@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = User::query()->where('email', 'bundle@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame(FeatureTier::Elite, $user->feature_tier);
        $this->assertSame((int) config('jvzoo.tier_credits.elite'), $user->story_credits);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_bundle_register_requires_session_tier(): void
    {
        $this->post(route('bundle.register.store'), [
            'name' => 'Bundle User',
            'email' => 'bundle2@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
            ->assertRedirect(route('bundle.register', ['access' => FeatureTier::Basic->value]));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'bundle2@example.com']);
    }
}
