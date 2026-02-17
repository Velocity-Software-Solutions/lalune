<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterSubscribedMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class NewsletterSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        // ✅ Honeypot field name (add this hidden input in BOTH forms)
        // <input type="text" name="website" value="" autocomplete="off" tabindex="-1" class="hidden" aria-hidden="true">
        // Bots will often fill it. Humans won't.
        $data = $request->validate([
            'email'   => ['required', 'email:rfc', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'], // honeypot
        ]);

        // If honeypot filled -> treat as bot
        if (!empty($data['website'])) {
            // quietly pretend success so bots don't learn
            return back()->with('success', 'Thank you for subscribing!');
        }

        // ✅ Optional: "minimum time" check (very effective)
        // Add in form: <input type="hidden" name="hp_time" value="{{ now()->timestamp }}">
        // If submitted too fast, likely bot.
        $hpTime = (int) $request->input('hp_time', 0);
        if ($hpTime > 0) {
            $elapsed = time() - $hpTime;
            if ($elapsed < 3) {
                return back()->with('success', 'Thank you for subscribing!');
            }
        }

        // Determine source (popup/footer) if you send it
        // Add in form: <input type="hidden" name="source" value="popup"> or "footer"
        $source = $request->input('source', 'unknown');

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
        if (!$existing) {
            $subscriber = NewsletterSubscriber::create([
                'email'              => $data['email'],
                'source'             => $source,
                'status'             => 'subscribed',
                'confirmation_token' => null,
                'confirmed_at'       => now(),
                'subscribed_at'      => now(),
                'unsubscribed_at'    => null,
            ]);
        } else {
            // If it exists but is NOT subscribed (e.g. unsubscribed/pending), re-subscribe
            $existing->fill([
                'source'             => $source,
                'status'             => 'subscribed',
                'confirmation_token' => null,
                'confirmed_at'       => now(),
                'subscribed_at'      => $existing->subscribed_at ?? now(),
                'unsubscribed_at'    => null,
            ])->save();

            $subscriber = $existing;
        }

        $unsubscribeUrl = route('newsletter.unsubscribe', $subscriber->email);
        $promoCode = 'WELCOME10';

        // Send welcome / subscribed email
        Mail::mailer('noreply')
            ->to($subscriber->email)
            ->send(new NewsletterSubscribedMail($promoCode, null, $unsubscribeUrl));

        return back()->with('success', 'Thank you for subscribing! Enjoy a 10% discount.');
    }

    public function confirm(string $token)
    {
        $subscriber = NewsletterSubscriber::where('confirmation_token', $token)
            ->where('status', 'pending')
            ->firstOrFail();

        $subscriber->update([
            'status'             => 'subscribed',
            'confirmation_token' => null,
            'confirmed_at'       => now(),
            'subscribed_at'      => now(),
        ]);

        $promoCode = 'WELCOME10';
        $unsubscribeUrl = route('newsletter.unsubscribe', $subscriber->email);

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
            'status'          => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        return redirect()->route('home')->with('success', 'You have been unsubscribed.');
    }
}
