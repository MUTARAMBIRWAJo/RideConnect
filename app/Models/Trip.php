<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    private const STATUS_MAP = [
        'pending' => 'PENDING',
        'accepted' => 'ACCEPTED',
        'started' => 'STARTED',
        'in_progress' => 'STARTED',
        'completed' => 'COMPLETED',
        'cancelled' => 'CANCELLED',
    ];

    protected $fillable = [
        'passenger_id',
        'driver_id',
        'pickup_location',
        'pickup_lat',
        'pickup_lng',
        'dropoff_location',
        'dropoff_lat',
        'dropoff_lng',
        'fare',
        'status',
        'requested_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'fare' => 'decimal:2',
        'requested_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function passenger()
    {
        return $this->belongsTo(MobileUser::class, 'passenger_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function setStatusAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['status'] = null;

            return;
        }

        $normalized = strtolower(trim((string) $value));

        $this->attributes['status'] = self::STATUS_MAP[$normalized] ?? strtoupper((string) $value);
    }
}
