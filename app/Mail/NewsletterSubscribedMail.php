<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterSubscribedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Optional data for the welcome email.
     */
    public ?string $promoCode;
    public ?string $shopUrl;
    public ?string $unsubscribeUrl;

    /**
     * Create a new message instance.
     *
     * @param  string|null  $promoCode
     * @param  string|null  $shopUrl
     * @param  string|null  $unsubscribeUrl
     */
    public function __construct(?string $promoCode = null, ?string $shopUrl = null, ?string $unsubscribeUrl = null)
    {
        $this->promoCode      = $promoCode;
        $this->shopUrl        = $shopUrl;
        $this->unsubscribeUrl = $unsubscribeUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to LaLune by NE',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.subscribed',
            with: [
                'promoCode'      => $this->promoCode,
                // fallback to products index if no custom shop URL passed
                'shopUrl'        => $this->shopUrl ?? route('home'),
                'unsubscribeUrl' => $this->unsubscribeUrl,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
