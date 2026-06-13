<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeatReservation extends Model
{
    use HasFactory;

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_RELEASED = 'released';
    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'ride_id',
        'booking_id',
        'trip_id',
        'passenger_id',
        'seats',
        'status',
        'reserved_at',
        'released_at',
    ];

    protected $casts = [
        'seats' => 'integer',
        'reserved_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function passenger()
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }
}
