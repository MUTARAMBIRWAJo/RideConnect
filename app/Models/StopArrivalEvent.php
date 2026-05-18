<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StopArrivalEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_route_assignment_id',
        'trip_id',
        'corridor_stop_id',
        'arrival_time',
        'departure_time',
        'is_terminal',
        'metadata',
    ];

    protected $casts = [
        'arrival_time' => 'datetime',
        'departure_time' => 'datetime',
        'is_terminal' => 'boolean',
        'metadata' => 'array',
    ];

    public function assignment()
    {
        return $this->belongsTo(BusRouteAssignment::class, 'bus_route_assignment_id');
    }

    public function stop()
    {
        return $this->belongsTo(CorridorStop::class, 'corridor_stop_id');
    }
}