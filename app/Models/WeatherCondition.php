<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeatherCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'location_lat',
        'location_lng',
        'weather_type',
        'temperature',
        'rain_intensity',
        'visibility',
        'wind_speed',
        'condition',
        'temperature_celsius',
        'wind_speed_kmh',
        'precipitation_mm',
        'weather_factor',
        'description',
        'recorded_at',
    ];

    protected $casts = [
        'location_lat' => 'decimal:7',
        'location_lng' => 'decimal:7',
        'temperature' => 'decimal:2',
        'rain_intensity' => 'decimal:2',
        'visibility' => 'decimal:2',
        'wind_speed' => 'decimal:2',
        'temperature_celsius' => 'decimal:2',
        'wind_speed_kmh' => 'decimal:2',
        'precipitation_mm' => 'decimal:2',
        'weather_factor' => 'decimal:4',
        'recorded_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
