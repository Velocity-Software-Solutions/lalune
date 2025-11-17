<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
        protected $fillable = [
        'email',
        'first_name',
        'last_name',
        'source',
        'status',
        'confirmation_token',
        'confirmed_at',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'confirmed_at'    => 'datetime',
        'subscribed_at'   => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];
}
