<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassengerBoardingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'passenger_route_boarding_id',
        'trip_id',
        'passenger_id',
        'boarding_stop_id',
        'destination_stop_id',
        'verified_by_driver_id',
        'status',
        'boarded_at',
        'verified_at',
        'metadata',
    ];

    protected $casts = [
        'boarded_at' => 'datetime',
        'verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function boarding()
    {
        return $this->belongsTo(PassengerRouteBoarding::class, 'passenger_route_boarding_id');
    }
}