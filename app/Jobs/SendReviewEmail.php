<?php

namespace App\Jobs;

use App\Mail\OrderReviewMail;
use App\Mail\ReviewRequestMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendReviewEmail implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** How long this job should be unique (in seconds). */
    public $uniqueFor = 86400; // 1 day; increase if you prefer

    public function __construct(public int $orderId)
    {
        //
    }

    public function uniqueId(): string
    {
        return 'review-email-for-order-' . $this->orderId;
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))->dontRelease(),
        ];
    }

    public function handle(): void
    {
        $order = Order::with(['items.product'])->find($this->orderId);
        if (!$order || !$order->email) {
            return;
        }

        Mail::mailer('noreply')
            ->to($order->email)
            ->send(new OrderReviewMail($order));

    }
}
