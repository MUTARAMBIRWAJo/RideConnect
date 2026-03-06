<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'amount',
        'platform_fee',
        'driver_amount',
        'currency',
        'payment_method',
        'payment_provider',
        'provider_transaction_id',
        'webhook_event_id',
        'verification_status',
        'transaction_id',
        'supabase_payment_id',
        'status',
        'payment_details',
        'paid_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'platform_fee'  => 'decimal:2',
        'driver_amount' => 'decimal:2',
        'paid_at'       => 'datetime',
        'refunded_at'   => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
