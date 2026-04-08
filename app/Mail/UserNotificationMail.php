<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;

class UserNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, string>  $lines
     */
    public function __construct(
        public string $subjectLine,
        public string $headline,
        public array $lines = [],
        public ?string $ctaLabel = null,
        public ?string $ctaUrl = null,
        public ?string $fromName = null,
        public ?string $fromAddress = null,
    ) {}

    public function envelope(): Envelope
    {
        $fromAddress = $this->fromAddress ?: config('mail.from.address');
        $fromName = $this->fromName ?: config('mail.from.name');

        return new Envelope(
            subject: $this->subjectLine,
            from: new Address($fromAddress, $fromName),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.user-notification',
            with: [
                'headline' => $this->headline,
                'lines' => $this->lines,
                'ctaLabel' => $this->ctaLabel,
                'ctaUrl' => $this->ctaUrl,
                'appName' => (string) config('app.name', 'DreamForge AI'),
            ],
        );
    }
}

