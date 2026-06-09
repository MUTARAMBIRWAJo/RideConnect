<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverAvailabilityCache extends Model
{
    protected $table = 'driver_availability_cache';

    protected $fillable = [
        'driver_id',
        'vehicle_type',
        'current_lat',
        'current_lng',
        'availability_score',
        'is_online',
        'is_available',
        'last_seen_at',
        'updated_at',
    ];

    protected $casts = [
        'current_lat' => 'decimal:7',
        'current_lng' => 'decimal:7',
        'availability_score' => 'decimal:2',
        'is_online' => 'boolean',
        'is_available' => 'boolean',
        'last_seen_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
