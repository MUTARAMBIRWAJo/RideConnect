<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusRouteAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_id',
        'corridor_id',
        'driver_id',
        'active_trip_id',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function bus()
    {
        return $this->belongsTo(Vehicle::class, 'bus_id');
    }

    public function corridor()
    {
        return $this->belongsTo(TransportCorridor::class, 'corridor_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function activeTrip()
    {
        return $this->belongsTo(Trip::class, 'active_trip_id');
    }

    public function positionUpdates()
    {
        return $this->hasMany(BusPositionUpdate::class, 'bus_route_assignment_id');
    }

    public function stopArrivals()
    {
        return $this->hasMany(StopArrivalEvent::class, 'bus_route_assignment_id');
    }
}