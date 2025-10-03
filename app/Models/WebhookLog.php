<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'type',
        'payload_json',
        'processed_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'processed_at' => 'datetime',
    ];
}
