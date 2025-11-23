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

    // Check if this email already exists
    $existing = NewsletterSubscriber::where('email', $data['email'])->first();

    // If already subscribed → show error
    if ($existing && $existing->status === 'subscribed') {
        return back()
            ->withErrors([
                'email' => 'This email is already subscribed to our newsletter.',
            ])
            ->withInput();
    }

    // If not existing → create new
    if (! $existing) {
        $subscriber = NewsletterSubscriber::create([
            'email'            => $data['email'],
            'source'           => 'popup',
            'status'           => 'subscribed',
            'confirmation_token' => null,
            'confirmed_at'     => now(),
            'subscribed_at'    => now(),
            'unsubscribed_at'  => null,
        ]);
    } else {
        // If it exists but is NOT subscribed (e.g. unsubscribed/pending), re-subscribe
        $existing->fill([
            'source'           => 'popup',
            'status'           => 'subscribed',
            'confirmation_token' => null,
            'confirmed_at'     => now(),
            'subscribed_at'    => $existing->subscribed_at ?? now(),
            'unsubscribed_at'  => null,
        ]);
        $existing->save();

        $subscriber = $existing;
    }

    $unsubscribeUrl = route('newsletter.unsubscribe', $subscriber->email); // adjust if you use token
    $promoCode = 'WELCOME10';

    // Send welcome / subscribed email
    Mail::mailer('noreply')
        ->to($subscriber->email)
        ->send(new NewsletterSubscribedMail($promoCode, null, $unsubscribeUrl));

    return back()->with(
        'success',
        'Thank you for subscribing! Enjoy a 10% discount.'
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
