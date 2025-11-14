<?php

namespace App\Mail;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The newsletter subscriber instance.
     */
    public NewsletterSubscriber $subscriber;

    /**
     * Create a new message instance.
     */
    public function __construct(NewsletterSubscriber $subscriber)
    {
        $this->subscriber = $subscriber;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your subscription to LaLune by NE',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $confirmUrl = route('newsletter.confirm', $this->subscriber->confirmation_token);

        return new Content(
            view: 'emails.newsletter.confirm',
            with: [
                'confirmUrl' => $confirmUrl,
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
