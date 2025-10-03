<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DraftOrder extends Model
{
    use HasFactory;

    protected $primaryKey = 'draft_order_id';
    
    protected $fillable = [
        'order_id',
        'payload',
        'shopify_draft_order_id',
        'invoice_url',
        'status',
        'total_price',
        'currency_code',
    ];

    protected $casts = [
        'payload' => 'array',
        'total_price' => 'decimal:2',
    ];

    /**
     * Get the booking associated with this draft order
     */
    public function booking()
    {
        return $this->hasOne(Booking::class, 'draft_order_id', 'draft_order_id');
    }

    /**
     * Scope for draft status
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    /**
     * Scope for completed status
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for cancelled status
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', 'cancelled');
    }
}
