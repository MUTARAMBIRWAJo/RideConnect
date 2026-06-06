<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripRequest extends Model
{
    /** @use HasFactory<\Database\Factories\TripRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'passenger_id',
        'corridor_id',
        'pickup_location',
        'pickup_lat',
        'pickup_lng',
        'dropoff_location',
        'dropoff_lat',
        'dropoff_lng',
        'matched_driver_id',
        'matched_vehicle_id',
        'distance_to_bus_km',
        'bus_eta_minutes',
        'trip_distance_km',
        'trip_duration_minutes',
        'estimated_fare',
        'currency',
        'status',
        'notes',
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:8',
        'pickup_lng' => 'decimal:8',
        'dropoff_lat' => 'decimal:8',
        'dropoff_lng' => 'decimal:8',
        'distance_to_bus_km' => 'decimal:2',
        'trip_distance_km' => 'decimal:2',
        'estimated_fare' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the passenger who made the request.
     */
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    /**
     * Get the corridor for this request.
     */
    public function corridor(): BelongsTo
    {
        return $this->belongsTo(TransportCorridor::class);
    }

    /**
     * Get the matched driver.
     */
    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'matched_driver_id');
    }

    /**
     * Get the matched vehicle.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'matched_vehicle_id');
    }

    /**
     * Check if request is still pending match.
     */
    public function isPendingMatch(): bool
    {
        return $this->status === 'PENDING_MATCH';
    }

    /**
     * Check if request is assigned.
     */
    public function isAssigned(): bool
    {
        return $this->status === 'BUS_ASSIGNED';
    }

    /**
     * Check if request is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    /**
     * Check if request is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }
};
