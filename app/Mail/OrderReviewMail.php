<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReviewMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $order;
    public $itemsForReview;

    public function __construct(Order $order)
    {
        $this->order = $order;
        $this->itemsForReview = $order
            ->items()                         // Order hasMany OrderItem
            ->whereNotNull('product_id')      // 👈 only real products
            // If you have a 'kind' column (e.g., 'product','shipping','tax'), use:
//      ->where('kind', 'product')
            ->with('product.images')
            ->get();

    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'How was your order ' . ($this->order->order_number ?? 'with us') . '?',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.order.review',
            with: [
                'order' => $this->order,
                'items' => $this->itemsForReview,
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
