<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralSetup extends Model
{
    protected $fillable = [
        'key',
        'content',
        'background_image',
    ];
}
