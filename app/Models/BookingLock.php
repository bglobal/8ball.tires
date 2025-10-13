<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingLock extends Model
{
    use HasFactory;
    protected $primaryKey = 'slot_key';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'slot_key',
        'taken',
    ];

    protected $casts = [
        'taken' => 'boolean',
    ];
}
