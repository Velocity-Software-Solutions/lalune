<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
       protected $fillable = [
        'name',
        'subject',
        'template_id',
        'body',
        'status',
        'segment',
        'scheduled_for',
        'sent_at',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'sent_at'       => 'datetime',
    ];

    public function sends()
    {
        return $this->hasMany(NewsletterCampaignSend::class, 'campaign_id');
    }
}
