<?php

namespace App\Models;

use App\BookingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    use HasFactory;
    protected $fillable = [
        'shop_id',
        'location_id',
        'service_id',
        'slot_start_utc',
        'slot_end_utc',
        'seats',
        'customer_name',
        'phone',
        'email',
        'status',
        'draft_order_id',
        'meta',
    ];

    protected $casts = [
        'slot_start_utc' => 'datetime',
        'slot_end_utc' => 'datetime',
        'status' => BookingStatus::class,
    ];

    // Custom accessor for meta field
    public function getMetaAttribute($value)
    {
        if (empty($value)) {
            return [];
        }
        
        // If it's already an array, return it
        if (is_array($value)) {
            return $value;
        }
        
        // If it's JSON, decode it
        if (is_string($value) && (str_starts_with($value, '{') || str_starts_with($value, '['))) {
            return json_decode($value, true) ?: [];
        }
        
        // If it's in simple format "key: value, key2: value2", parse it
        if (is_string($value) && str_contains($value, ':')) {
            $pairs = explode(',', $value);
            $result = [];
            foreach ($pairs as $pair) {
                $pair = trim($pair);
                if (str_contains($pair, ':')) {
                    [$key, $val] = explode(':', $pair, 2);
                    $result[trim($key)] = trim($val);
                }
            }
            return $result;
        }
        
        return [];
    }

    // Custom mutator for meta field
    public function setMetaAttribute($value)
    {
        if (is_string($value)) {
            // If it's in simple format, convert to array
            if (str_contains($value, ':') && !str_starts_with($value, '{')) {
                $pairs = explode(',', $value);
                $result = [];
                foreach ($pairs as $pair) {
                    $pair = trim($pair);
                    if (str_contains($pair, ':')) {
                        [$key, $val] = explode(':', $pair, 2);
                        $result[trim($key)] = trim($val);
                    }
                }
                $this->attributes['meta'] = json_encode($result);
            } else {
                $this->attributes['meta'] = $value;
            }
        } else {
            $this->attributes['meta'] = json_encode($value);
        }
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function draftOrder(): BelongsTo
    {
        return $this->belongsTo(DraftOrder::class, 'draft_order_id', 'draft_order_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
