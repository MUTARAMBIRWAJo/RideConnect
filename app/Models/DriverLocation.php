<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'trip_id',
        'latitude',
        'longitude',
        'lat',
        'lng',
        'speed',
        'speed_kmh',
        'heading',
        'accuracy',
        'recorded_at',
        'updated_at',
        'last_activity_at',
        'is_online',
    ];

    public $timestamps = false;

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
        'speed' => 'decimal:2',
        'speed_kmh' => 'decimal:2',
        'heading' => 'decimal:1',
        'accuracy' => 'decimal:2',
        'updated_at' => 'datetime',
        'recorded_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'is_online' => 'boolean',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }
}
