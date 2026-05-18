<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusPositionUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_route_assignment_id',
        'trip_id',
        'latitude',
        'longitude',
        'speed_kph',
        'heading_degrees',
        'next_stop_id',
        'eta_minutes',
        'route_progress_percent',
        'captured_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'speed_kph' => 'decimal:2',
        'route_progress_percent' => 'decimal:2',
        'captured_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(BusRouteAssignment::class, 'bus_route_assignment_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function nextStop()
    {
        return $this->belongsTo(CorridorStop::class, 'next_stop_id');
    }
}