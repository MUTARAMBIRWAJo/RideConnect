<?php

namespace App\Models;

use App\Models\V3\TripV3;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverTripOffer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'trip_id',
        'driver_id',
        'status',
        'expires_at',
        'responded_at',
        'response_reason',
        'payload',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
        'payload' => 'array',
    ];

    public function trip(): BelongsTo
    {
        return $this->belongsTo(TripV3::class, 'trip_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
