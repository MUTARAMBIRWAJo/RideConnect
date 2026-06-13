<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'matching_session_id',
        'passenger_id',
        'transport_type',
        'pickup_lat',
        'pickup_lng',
        'dropoff_lat',
        'dropoff_lng',
        'selected_driver_id',
        'payload',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];

    public function passenger()
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'selected_driver_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
