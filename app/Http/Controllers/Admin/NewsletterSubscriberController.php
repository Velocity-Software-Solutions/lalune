<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterConfirmMail;
use App\Mail\NewsletterSubscribedMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Mail;
use Str;

class NewsletterSubscriberController extends Controller
{
    public function index(Request $request)
    {
        // Filters from query string
        $status = $request->query('status');   // pending | subscribed | unsubscribed | bounced
        $source = $request->query('source');   // popup | checkout | manual | ...
        $search = $request->query('q');        // email search
        $from = $request->query('from');     // subscribed from date (Y-m-d)
        $to = $request->query('to');       // subscribed to date (Y-m-d)

        $query = NewsletterSubscriber::query()->where('status', '!=', 'deleted');

        // Status filter
        if ($status && in_array($status, ['pending', 'subscribed', 'unsubscribed', 'bounced'], true)) {
            $query->where('status', $status);
        }

        // Source filter (optional)
        if ($source) {
            $query->where('source', $source);
        }

        // Email search
        if ($search) {
            $query->where('email', 'like', '%' . $search . '%');
        }

        // Date range filter on subscribed_at
        if ($from) {
            $query->whereDate('subscribed_at', '>=', $from);
        }

        if ($to) {
            $query->whereDate('subscribed_at', '<=', $to);
        }

        // Order newest first
        $subscribers = $query
            ->latest('id')
            ->paginate(50)
            ->appends($request->query()); // keep filters in pagination links

        // For dropdown/status tabs
        $statusOptions = [
            'pending' => 'Pending',
            'subscribed' => 'Subscribed',
            'unsubscribed' => 'Unsubscribed',
            'bounced' => 'Bounced',
        ];

        // Optional sources (you can build this dynamically too)
        $sourceOptions = [
            'popup' => 'Popup',
            'checkout' => 'Checkout',
            'manual' => 'Manual',
        ];

        return view('admin.newsletter.subscribers.index', compact(
            'subscribers',
            'statusOptions',
            'sourceOptions',
            'status',
            'source',
            'search',
            'from',
            'to'
        ));
    }

    public function show(NewsletterSubscriber $subscriber)
    {
        return view('admin.newsletter.subscribers.show', compact('subscriber'));
    }


    public function resendConfirm(NewsletterSubscriber $subscriber)
    {
        // Only allow for pending
        if ($subscriber->status !== 'pending') {
            return back()->with('error', 'Only pending subscribers can receive a confirmation email.');
        }

        // Ensure there is a token
        if (empty($subscriber->confirmation_token)) {
            $subscriber->confirmation_token = Str::random(40);
            $subscriber->save();
        }

        Mail::mailer('noreply')
            ->to($subscriber->email)
            ->send(new NewsletterConfirmMail($subscriber));

        return back()->with('success', 'Confirmation email resent to ' . $subscriber->email . '.');
    }

    public function confirmAllPending()
    {
        $promoCode = 'WELCOME10';
        $sentCount = 0;

        NewsletterSubscriber::where('status', 'pending')
            ->orderBy('id')
            ->chunkById(100, function ($subscribers) use (&$sentCount, $promoCode) {
                foreach ($subscribers as $subscriber) {

                    // Build unsubscribe URL (adjust if you use tokens instead of raw email)
                    $unsubscribeUrl = route('newsletter.unsubscribe', $subscriber->email);

                    // Send the "subscribed" email
                    Mail::mailer('noreply')
                        ->to($subscriber->email)
                        ->send(new NewsletterSubscribedMail(
                            $promoCode,
                            null,            // pass name or extra data if your mailable expects it
                            $unsubscribeUrl
                        ));

                    // Update subscriber status → subscribed
                    $subscriber->status = 'subscribed';
                    $subscriber->confirmed_at = now();
                    $subscriber->subscribed_at = $subscriber->subscribed_at ?? now();
                    $subscriber->unsubscribed_at = null;
                    $subscriber->confirmation_token = null;

                    $subscriber->save();

                    $sentCount++;
                }
            });

        return back()->with('success', "Welcome email sent and status updated for {$sentCount} pending subscribers.");
    }
    public function unsubscribe(NewsletterSubscriber $subscriber)
    {
        // If already unsubscribed, just return
        if ($subscriber->status === 'unsubscribed') {
            return back()->with('info', $subscriber->email . ' is already unsubscribed.');
        }

        $subscriber->update([
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
        ]);

        return back()->with('success', $subscriber->email . ' has been unsubscribed.');
    }

    public function bulk(Request $request)
{
    $data = $request->validate([
        'action' => ['required', Rule::in(['subscribe', 'unsubscribe', 'delete'])],
        'ids'    => ['required', 'array', 'min:1'],
        'ids.*'  => ['integer'],
    ]);

    $ids = array_values(array_unique($data['ids']));
    $action = $data['action'];

    $query = NewsletterSubscriber::query()->whereIn('id', $ids);

    // If you want: restrict to only visible subscribers by your filters, you’d add more constraints here.

    $affected = 0;

    
    if ($action === 'subscribe') {
        // Only unsubscribe subscribed users (optional safety)
        $affected = (clone $query)
            ->where('status', '!=', 'subscribed')
            ->update(['status' => 'subscribed']);
    }

    if ($action === 'unsubscribe') {
        // Only unsubscribe subscribed users (optional safety)
        $affected = (clone $query)
            ->where('status', 'subscribed')
            ->update(['status' => 'unsubscribed']);
    }

    if ($action === 'delete') {
        $affected = (clone $query)->where('status', '!=', 'deleted')
            ->update(['status' => 'deleted']);;
    }

    return back()->with('success', "{$action}d {$affected} subscriber(s).");
}
}
