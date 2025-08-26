<?php

use Illuminate\Mail\Events\MessageSent;
use App\Models\EmailLog;
use App\Models\Order;

class LogMailSent
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;
        $to = array_keys($message->getTo() ?? []);
        $subject = $message->getSubject();
        $messageId = $message->getHeaders()->get('message-id')?->getBody();

        // Try to pull the Order from the Mailable (if passed)
        $order = $event->data['order'] ?? null;

        foreach ($to as $email) {
            EmailLog::create([
                'to_email' => $email,
                'subject' => $subject,
                'message_id' => $messageId,
                'status' => 'sent',
                'order_id' => $order instanceof Order ? $order->id : null,
            ]);
        }
    }
}