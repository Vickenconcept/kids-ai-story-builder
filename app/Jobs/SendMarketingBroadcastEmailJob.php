<?php

namespace App\Jobs;

use App\Mail\MarketingBroadcastMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMarketingBroadcastEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Hard cap for fatal failures; {@see retryUntil()} allows many more queue
     * re-dispatches when middleware releases the job for rate limiting.
     */
    public int $tries = 100;

    /**
     * Keep throttling releases from counting as terminal failures for a while.
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(3);
    }

    /**
     * Throttle sends so workers stay under Resend’s per-second quota.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new RateLimited('resend-marketing-mail')];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30, 60, 120];
    }

    public function __construct(
        public string $toEmail,
        public string $subjectLine,
        public string $htmlBody,
    ) {
        $this->onQueue('marketing-mail');
    }

    public function handle(): void
    {
        Mail::to($this->toEmail)->send(new MarketingBroadcastMail($this->subjectLine, $this->htmlBody));

        // Extra pacing so a single worker cannot burst past Resend’s 5 req/sec even if the
        // cache-based limiter and other app traffic share the same second window.
        usleep(300_000);
    }
}
