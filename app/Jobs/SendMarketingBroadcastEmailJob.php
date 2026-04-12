<?php

namespace App\Jobs;

use App\Mail\MarketingBroadcastMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMarketingBroadcastEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 300];
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
