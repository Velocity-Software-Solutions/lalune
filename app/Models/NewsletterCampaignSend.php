<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaignSend extends Model
{
        protected $fillable = [
        'campaign_id',
        'subscriber_id',
        'status',
        'sent_at',
        'opened_at',
        'clicked_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at'    => 'datetime',
        'opened_at'  => 'datetime',
        'clicked_at' => 'datetime',
    ];

    public function campaign()
    {
        return $this->belongsTo(NewsletterCampaign::class, 'campaign_id');
    }

    public function subscriber()
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }
}
