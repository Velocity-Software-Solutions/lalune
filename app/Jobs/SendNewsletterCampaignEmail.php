<?php
namespace App\Jobs;

use App\Models\NewsletterCampaignSend;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendNewsletterCampaignEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $sendId;

    public function __construct(int $sendId)
    {
        $this->sendId = $sendId;
    }

    public function handle(): void
    {
        $send = NewsletterCampaignSend::with(['campaign', 'subscriber'])->find($this->sendId);

        if (! $send || ! $send->campaign || ! $send->subscriber) {
            return;
        }

        $campaign   = $send->campaign;
        $subscriber = $send->subscriber;

        // If already sent or failed, skip
        if ($send->status === 'sent' || $send->status === 'failed') {
            return;
        }

        // Basic safety check: don't send to unsubscribed
        if ($subscriber->status === 'unsubscribed') {
            $send->update([
                'status'        => 'failed',
                'error_message' => 'Subscriber is unsubscribed.',
            ]);
            return;
        }

        // Send the mail (you can swap to a dedicated Mailable if you prefer)
        Mail::mailer('noreply')->send(
            'emails.newsletter.campaign', // view
            [
                'campaign'   => $campaign,
                'subscriber' => $subscriber,
            ],
            function ($message) use ($campaign, $subscriber) {
                $message->to($subscriber->email)
                    ->subject($campaign->subject);
            }
        );

        $send->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);
    }
}
