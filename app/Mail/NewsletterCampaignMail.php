<?php

namespace App\Mail;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewsletterCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public NewsletterCampaign $campaign;
    public NewsletterSubscriber $subscriber;
    public string $unsubscribeUrl;
    public ?string $webviewUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(
        NewsletterCampaign $campaign,
        NewsletterSubscriber $subscriber,
        string $unsubscribeUrl,
        ?string $webviewUrl = null
    ) {
        $this->campaign      = $campaign;
        $this->subscriber    = $subscriber;
        $this->unsubscribeUrl = $unsubscribeUrl;
        $this->webviewUrl    = $webviewUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->campaign->subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.newsletter.campaign',
            with: [
                'campaign'       => $this->campaign,
                'subscriber'     => $this->subscriber,
                'unsubscribeUrl' => $this->unsubscribeUrl,
                'webviewUrl'     => $this->webviewUrl,
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
