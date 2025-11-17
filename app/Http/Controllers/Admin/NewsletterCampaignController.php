<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletterCampaignEmail;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignSend;
use App\Models\NewsletterSubscriber;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NewsletterCampaignController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status'); // draft | scheduled | sending | sent
        $search = $request->query('q');      // search by name/subject

        $query = NewsletterCampaign::query();

        if ($status && in_array($status, ['draft', 'scheduled', 'sending', 'sent'], true)) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('subject', 'like', '%' . $search . '%');
            });
        }

        // Load send counts and basic stats
        $campaigns = $query
            ->withCount([
                'sends as total_sends',
                'sends as opens_count' => function ($q) {
                    $q->whereNotNull('opened_at');
                },
                'sends as clicks_count' => function ($q) {
                    $q->whereNotNull('clicked_at');
                },
            ])
            ->latest('created_at')
            ->paginate(20)
            ->appends($request->query());

        $statusOptions = [
            'draft'     => 'Draft',
            'scheduled' => 'Scheduled',
            'sending'   => 'Sending',
            'sent'      => 'Sent',
        ];

        return view('admin.newsletter.campaigns.index', compact(
            'campaigns',
            'statusOptions',
            'status',
            'search'
        ));
    }

    // Stubs for now – we’ll implement later
  public function create()
    {
        // Segments
        $segmentOptions = [
            'all_subscribed'      => 'All subscribed',
            'only_pending'        => 'Only pending (resend confirm)',
            'custom_subscribers'  => 'Specific subscribers',
        ];

        // For the custom selection
        $subscribers = NewsletterSubscriber::orderBy('email')
            ->get(['id', 'email', 'status']);

        return view('admin.newsletter.campaigns.create', compact(
            'segmentOptions',
            'subscribers',
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'subject'       => ['required', 'string', 'max:255'],
            'body'          => ['required', 'string'],
            'segment'       => ['required', 'in:all_subscribed,only_pending,custom_subscribers'],
            'scheduled_for' => ['nullable', 'date'],
            'subscriber_ids'   => ['array', 'required_if:segment,custom_subscribers'],
            'subscriber_ids.*' => ['integer', 'exists:newsletter_subscribers,id'],
        ]);

        // Create campaign
        $campaign = NewsletterCampaign::create([
            'name'          => $data['name'],
            'subject'       => $data['subject'],
            'body'          => $data['body'],
            'segment'       => $data['segment'],
            'status'        => $data['scheduled_for'] ? 'scheduled' : 'draft',
            'scheduled_for' => $data['scheduled_for'] ?? null,
        ]);

        // Figure out base time for sending
        $baseTime = $data['scheduled_for']
            ? Carbon::parse($data['scheduled_for'])
            : now();

        // Resolve recipients
        if ($data['segment'] === 'all_subscribed') {
            $recipients = NewsletterSubscriber::where('status', 'subscribed')->get();
        } elseif ($data['segment'] === 'only_pending') {
            $recipients = NewsletterSubscriber::where('status', 'pending')->get();
        } else {
            // custom_subscribers
            $recipients = NewsletterSubscriber::whereIn('id', $data['subscriber_ids'] ?? [])->get();
        }

        // Create send records + queue jobs spaced 5 minutes apart
        foreach ($recipients as $index => $subscriber) {
            // Create send record as pending
            $send = NewsletterCampaignSend::create([
                'campaign_id'  => $campaign->id,
                'subscriber_id'=> $subscriber->id,
                'status'       => 'pending',
            ]);

            // Each subscriber 5 minutes apart
            $sendAt = (clone $baseTime)->addMinutes($index * 5);

            // Queue job with delay
            SendNewsletterCampaignEmail::dispatch($send->id)
                ->delay($sendAt);
        }

        return redirect()
            ->route('admin.newsletter.campaigns.edit', $campaign)
            ->with('success', 'Campaign created and emails scheduled. They will send starting at the scheduled time, 5 minutes apart per subscriber.');
    }




    public function edit(NewsletterCampaign $campaign)
    {
        return view('admin.newsletter.campaigns.edit', compact('campaign'));
    }

    public function update(Request $request, NewsletterCampaign $campaign)
    {
        // to be implemented later
    }
}
