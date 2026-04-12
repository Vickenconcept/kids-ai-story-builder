<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendMarketingBroadcastEmailJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MarketingMailTest extends TestCase
{
    use RefreshDatabase;

    private function setStoryAdminEmails(string $email): void
    {
        config(['story.admin_emails' => [strtolower(trim($email))]]);
    }

    public function test_guest_is_redirected_from_marketing_mail(): void
    {
        $this->get(route('admin.marketing-mail.index'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_marketing_mail(): void
    {
        $this->setStoryAdminEmails('admin@example.com');
        $user = User::factory()->create(['email' => 'member@example.com']);

        $this->actingAs($user)
            ->get(route('admin.marketing-mail.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_marketing_mail_page(): void
    {
        $this->setStoryAdminEmails('admin@example.com');
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $this->actingAs($admin)
            ->get(route('admin.marketing-mail.index'))
            ->assertOk();
    }

    public function test_admin_send_dispatches_one_job_per_recipient(): void
    {
        Bus::fake();

        $this->setStoryAdminEmails('admin@example.com');
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $this->actingAs($admin)
            ->from(route('admin.marketing-mail.index'))
            ->post(route('admin.marketing-mail.send'), [
                'subject' => 'Hello',
                'body_html' => '<p>Test body</p>',
                'user_ids' => [],
                'extra_emails' => 'one@test.com, two@test.com',
            ])
            ->assertRedirect();

        Bus::assertDispatchedTimes(SendMarketingBroadcastEmailJob::class, 2);
        Bus::assertDispatched(
            SendMarketingBroadcastEmailJob::class,
            fn (SendMarketingBroadcastEmailJob $job): bool => $job->toEmail === 'one@test.com'
                && $job->subjectLine === 'Hello'
                && str_contains($job->htmlBody, 'Test body'),
        );
        Bus::assertDispatched(
            SendMarketingBroadcastEmailJob::class,
            fn (SendMarketingBroadcastEmailJob $job): bool => $job->toEmail === 'two@test.com',
        );
    }

    public function test_send_requires_at_least_one_recipient(): void
    {
        $this->setStoryAdminEmails('admin@example.com');
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        $this->actingAs($admin)
            ->post(route('admin.marketing-mail.send'), [
                'subject' => 'Hello',
                'body_html' => '<p>Test</p>',
                'user_ids' => [],
                'extra_emails' => '',
            ])
            ->assertSessionHasErrors('recipients');
    }
}
