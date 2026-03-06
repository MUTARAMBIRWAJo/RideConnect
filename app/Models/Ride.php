<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'vehicle_id',
        'origin_address',
        'origin_lat',
        'origin_lng',
        'destination_address',
        'destination_lat',
        'destination_lng',
        'departure_time',
        'arrival_time_estimated',
        'available_seats',
        'price_per_seat',
        'currency',
        'description',
        'status',
        'ride_type',
        'luggage_allowed',
        'pets_allowed',
        'smoking_allowed',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'origin_lat' => 'decimal:8',
        'origin_lng' => 'decimal:8',
        'destination_lat' => 'decimal:8',
        'destination_lng' => 'decimal:8',
        'departure_time' => 'datetime',
        'arrival_time_estimated' => 'datetime',
        'price_per_seat' => 'decimal:2',
        'available_seats' => 'integer',
        'luggage_allowed' => 'boolean',
        'pets_allowed' => 'boolean',
        'smoking_allowed' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
