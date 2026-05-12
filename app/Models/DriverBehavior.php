<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverBehavior extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'trip_id',
        'rating',
        'acceptance_rate',
        'cancellation_rate',
        'on_time_rate',
        'driving_score',
        'behavior_score',
        'notes',
        'reviewed_at',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'acceptance_rate' => 'decimal:4',
        'cancellation_rate' => 'decimal:4',
        'on_time_rate' => 'decimal:4',
        'driving_score' => 'decimal:4',
        'behavior_score' => 'decimal:4',
        'reviewed_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
