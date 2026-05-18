<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripAssignmentAttempt extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_NOTIFIED = 'notified';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'trip_id',
        'driver_id',
        'score',
        'score_breakdown',
        'rejection_reason',
        'status',
        'expires_at',
        'responded_at',
    ];

    protected $casts = [
        'score' => 'decimal:4',
        'score_breakdown' => 'array',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
