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

    public int $tries = 5;

    /**
     * Serialize sends so parallel workers cannot exceed Resend’s per-second quota.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new RateLimited('resend-marketing-mail'))];
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
    ) {}

    public function handle(): void
    {
        Mail::to($this->toEmail)->send(new MarketingBroadcastMail($this->subjectLine, $this->htmlBody));
    }
}
