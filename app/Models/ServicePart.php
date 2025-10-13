<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePart extends Model
{
    use HasFactory;
    protected $fillable = [
        'service_id',
        'shopify_variant_gid',
        'shopify_product_id',
        'product_title',
        'qty_per_service',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
