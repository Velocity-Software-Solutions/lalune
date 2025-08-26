<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Barryvdh\DomPDF\Facade\Pdf;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

   public function build()
{
    $pdf = Pdf::loadView('checkout.receipt', ['order' => $this->order]);

    return $this->subject('Your Order Confirmation')
                ->markdown('emails.order.confirmation')
                ->with(['order' => $this->order]) // ✅ ensures it's available in $event->data
                ->attachData($pdf->output(), 'Receipt-' . $this->order->order_number . '.pdf', [
                    'mime' => 'application/pdf',
                ]);
}

}
