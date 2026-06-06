<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MotorcycleTrip extends Model
{
    protected $table = 'motorcycle_trips';

    protected $fillable = [
        'passenger_id',
        'driver_id',
        'vehicle_id',
        'pickup_location',
        'pickup_lat',
        'pickup_lng',
        'dropoff_location',
        'dropoff_lat',
        'dropoff_lng',
        'distance_km',
        'duration_minutes',
        'estimated_fare',
        'actual_fare',
        'currency',
        'status',
        'retry_count',
        'max_retries',
        'matching_status',
        'last_retry_at',
        'initial_search_radius_km',
        'current_search_radius_km',
        'rejected_driver_id',
        'rejection_reason',
        'rejected_drivers',
        'requested_at',
        'matching_started_at',
        'assigned_at',
        'accepted_at',
        'driver_arrived_at',
        'started_at',
        'completed_at',
        'rejected_at',
        'cancelled_at',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'pickup_lat' => 'float',
        'pickup_lng' => 'float',
        'dropoff_lat' => 'float',
        'dropoff_lng' => 'float',
        'distance_km' => 'float',
        'estimated_fare' => 'float',
        'actual_fare' => 'float',
        'initial_search_radius_km' => 'float',
        'current_search_radius_km' => 'float',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'rejected_drivers' => 'array',
        'metadata' => 'array',
        'requested_at' => 'datetime',
        'matching_started_at' => 'datetime',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'driver_arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_retry_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function rejectedDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'rejected_driver_id');
    }

    /**
     * Status checks
     */
    public function isRequested(): bool
    {
        return $this->status === 'REQUESTED';
    }

    public function isMatching(): bool
    {
        return $this->status === 'MATCHING';
    }

    public function isAssigned(): bool
    {
        return $this->status === 'ASSIGNED';
    }

    public function isDriverAssigned(): bool
    {
        return $this->status === 'DRIVER_ASSIGNED';
    }

    public function isWaitingPassenger(): bool
    {
        return $this->status === 'PASSENGER_WAITING';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'IN_PROGRESS';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    public function isRejected(): bool
    {
        return $this->status === 'REJECTED_BY_DRIVER';
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['CANCELLED_BY_PASSENGER', 'CANCELLED_BY_DRIVER']);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [
            'ASSIGNED',
            'DRIVER_ASSIGNED',
            'PASSENGER_WAITING',
            'IN_PROGRESS',
        ]);
    }

    /**
     * Scope: get active trips for driver
     */
    public function scopeActiveForDriver($query, int $driverId)
    {
        return $query->where('driver_id', $driverId)
            ->whereIn('status', [
                'ASSIGNED',
                'DRIVER_ASSIGNED',
                'PASSENGER_WAITING',
                'IN_PROGRESS',
            ]);
    }

    /**
     * Scope: get trips by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: get recent trips
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Scope: get active trips (not completed or cancelled)
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            'REQUESTED',
            'MATCHING',
            'ASSIGNED',
            'DRIVER_ASSIGNED',
            'PASSENGER_WAITING',
            'IN_PROGRESS',
        ]);
    }
}
