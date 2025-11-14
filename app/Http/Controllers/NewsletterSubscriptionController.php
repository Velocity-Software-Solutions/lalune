<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterConfirmMail;
use App\Mail\NewsletterSubscribedMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mail;

class NewsletterSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
        ]);

        $token = Str::random(40);

        // Create or refresh an existing subscriber
        $subscriber = NewsletterSubscriber::updateOrCreate(
            ['email' => $data['email']],
            [
                'source' => 'popup',
                'status' => 'pending',
                'confirmation_token' => $token,
                'confirmed_at' => null,
                'subscribed_at' => null, // will be set on confirm
                'unsubscribed_at' => null,
            ]
        );

        // Send confirmation email
        Mail::mailer('noreply')
            ->to($subscriber->email)
            ->send(new NewsletterConfirmMail($subscriber));

        return back()->with(
            'success',
            'Thank you for subscribing! Please check your email to confirm your subscription.'
        );
    }

    public function confirm(string $token)
    {
        $subscriber = NewsletterSubscriber::where('confirmation_token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        $subscriber->update([
            'status' => 'subscribed',
            'confirmation_token' => null,
            'confirmed_at' => now(),
            'subscribed_at' => now(),
        ]);

        // Generate or fetch a promo code if you want
        $promoCode = 'WELCOME10';

        // Build unsubscribe URL if you have a route for it
        // e.g. route('newsletter.unsubscribe', $subscriber->unsubscribe_token)
        $unsubscribeUrl = route('newsletter.unsubscribe', $subscriber->email); // or your real URL

        Mail::mailer('noreply')
            ->to($subscriber->email)
            ->send(new NewsletterSubscribedMail($promoCode, null, $unsubscribeUrl));

        return redirect()
            ->route('home')
            ->with('success', 'Your subscription to LaLune by NE is confirmed.');
    }

    public function unsubscribe(string $email)
    {
        $subscriber = NewsletterSubscriber::where('email', $email)->firstOrFail();

        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        return redirect()->route('home')->with('success', 'You have been unsubscribed.');
    }
}
