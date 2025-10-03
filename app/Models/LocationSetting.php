<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationSetting extends Model
{
    use HasFactory;
    protected $fillable = [
        'location_id',
        'slot_duration_minutes',
        'open_time',
        'close_time',
        'is_weekend_open',
        'weekend_open_time',
        'weekend_close_time',
        'capacity_per_slot',
    ];

    protected $casts = [
        'open_time' => 'datetime:H:i',
        'close_time' => 'datetime:H:i',
        'is_weekend_open' => 'boolean',
        'weekend_open_time' => 'datetime:H:i',
        'weekend_close_time' => 'datetime:H:i',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
