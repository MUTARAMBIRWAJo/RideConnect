<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'driver_id',
        'passenger_id',
        'event_type',
        'metadata',
        'event_time',
    ];

    protected $casts = [
        'metadata' => 'array',
        'event_time' => 'datetime',
    ];
}
