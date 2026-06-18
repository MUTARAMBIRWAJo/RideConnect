<?php

namespace App\Models\V3;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripV3 extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'trips_v3';

    protected $attributes = [
        'status' => 'REQUESTED',
        'driver_response_status' => 'pending',
        'match_attempt_count' => 0,
    ];

    protected $fillable = [
        'user_id',
        'driver_id',
        'transport_type',
        'status',
        'pickup_location',
        'dropoff_location',
        'pickup_lat',
        'pickup_lng',
        'dropoff_lat',
        'dropoff_lng',
        'fare_estimate',
        'fare_actual',
        'metadata',
        'driver_response_status',
        'matched_driver_id',
        'match_attempt_count',
        'last_matched_at',
        'ignored_driver_ids',
    ];

    protected $casts = [
        'metadata' => 'array',
        'ignored_driver_ids' => 'array',
        'last_matched_at' => 'datetime',
        'pickup_lat' => 'float',
        'pickup_lng' => 'float',
        'dropoff_lat' => 'float',
        'dropoff_lng' => 'float',
        'fare_estimate' => 'float',
        'fare_actual' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
