<?php

namespace App\Models\V3;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class DriverLocationV3 extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'driver_locations_v3';

    protected $fillable = [
        'driver_id',
        'trip_id',
        'lat',
        'lng',
        'latitude',
        'longitude',
        'heading',
        'speed',
        'is_online',
    ];

    protected $casts = [
        'lat' => 'double',
        'lng' => 'double',
        'latitude' => 'double',
        'longitude' => 'double',
        'heading' => 'float',
        'speed' => 'float',
        'is_online' => 'boolean',
    ];

    public function driver()
    {
        return $this->belongsTo(\App\Models\Driver::class);
    }
}
