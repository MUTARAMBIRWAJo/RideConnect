<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassengerRouteBoarding extends Model
{
    use HasFactory;

    protected $fillable = [
        'passenger_id',
        'trip_id',
        'corridor_id',
        'bus_route_assignment_id',
        'boarding_stop_id',
        'destination_stop_id',
        'ticket_code',
        'qr_payload',
        'seats_reserved',
        'fare_amount',
        'payment_status',
        'status',
        'boarded_at',
        'completed_at',
    ];

    protected $casts = [
        'qr_payload' => 'array',
        'seats_reserved' => 'integer',
        'fare_amount' => 'decimal:2',
        'boarded_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function passenger()
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function corridor()
    {
        return $this->belongsTo(TransportCorridor::class, 'corridor_id');
    }

    public function busRouteAssignment()
    {
        return $this->belongsTo(BusRouteAssignment::class, 'bus_route_assignment_id');
    }

    public function boardingStop()
    {
        return $this->belongsTo(CorridorStop::class, 'boarding_stop_id');
    }

    public function destinationStop()
    {
        return $this->belongsTo(CorridorStop::class, 'destination_stop_id');
    }

    public function boardingEvents()
    {
        return $this->hasMany(PassengerBoardingEvent::class, 'passenger_route_boarding_id');
    }
}