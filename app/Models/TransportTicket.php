<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportTicket extends Model
{
    use HasFactory;

    public const STATUS_ISSUED = 'issued';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'ticket_code',
        'qr_payload',
        'ride_id',
        'trip_id',
        'booking_id',
        'passenger_id',
        'driver_id',
        'seat_count',
        'payment_status',
        'status',
        'issued_at',
        'validated_at',
    ];

    protected $casts = [
        'qr_payload' => 'array',
        'seat_count' => 'integer',
        'issued_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function passenger()
    {
        return $this->belongsTo(MobileUser::class, 'passenger_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
