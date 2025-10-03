<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Ramsey\Uuid\Uuid;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'shopify_domain',
        'admin_api_token',
        'currency',
    ];

    protected $casts = [
        'admin_api_token' => 'encrypted',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->public_id)) {
                $model->public_id = Uuid::uuid4()->toString();
            }
        });
    }


    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
