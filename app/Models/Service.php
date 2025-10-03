<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;
    protected $fillable = [
        'shop_id',
        'title',
        'slug',
        'type',
        'duration_minutes',
        'price_cents',
        'price', // Virtual attribute
        'active',
        'shopify_variant_gid',
        'shopify_product_id',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    // Accessor for price in dollars
    public function getPriceAttribute()
    {
        return $this->price_cents / 100;
    }

    // Mutator for price in dollars
    public function setPriceAttribute($value)
    {
        $this->attributes['price_cents'] = round($value * 100);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function serviceParts(): HasMany
    {
        return $this->hasMany(ServicePart::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
