<?php
namespace App\Jobs;

use App\Mail\NewsletterCampaignMail;
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

    if ($send->status === 'sent' || $send->status === 'failed') {
        return;
    }

    // Don’t send to unsubscribed
    if ($subscriber->status === 'unsubscribed') {
        $send->update([
            'status'        => 'failed',
            'error_message' => 'Subscriber is unsubscribed.',
        ]);
        return;
    }

    // Build URLs (adjust these routes to match your app)
        $unsubscribeUrl = route('newsletter.unsubscribe', $subscriber->email); // or your real URL


    $webviewUrl = null;
    // If you later create a webview route:
    // $webviewUrl = route('newsletter.campaign.webview', $campaign);

    // Send via mailable
    Mail::mailer('noreply')
        ->to($subscriber->email)
        ->send(new NewsletterCampaignMail(
            $campaign,
            $subscriber,
            $unsubscribeUrl,
            $webviewUrl
        ));

    $send->update([
        'status'  => 'sent',
        'sent_at' => now(),
    ]);
}

}
