<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteState extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'route_id',
        'pickup_lat',
        'pickup_lng',
        'dropoff_lat',
        'dropoff_lng',
        'route_name',
        'distance_km',
        'estimated_duration_min',
        'traffic_level',
        'road_condition',
        'average_speed',
        'incident_flag',
        'congestion_index',
        'route_geometry',
    ];

    protected $casts = [
        'route_id' => 'integer',
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'distance_km' => 'decimal:3',
        'estimated_duration_min' => 'integer',
        'traffic_level' => 'integer',
        'average_speed' => 'decimal:2',
        'incident_flag' => 'boolean',
        'congestion_index' => 'decimal:4',
        'route_geometry' => 'array',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }
}
