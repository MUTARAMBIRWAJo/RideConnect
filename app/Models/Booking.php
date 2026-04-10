<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    private const STATUS_MAP = [
        'pending' => 'pending',
        'confirmed' => 'confirmed',
        'cancelled' => 'cancelled',
        'completed' => 'completed',
        'no_show' => 'no_show',
    ];

    protected $fillable = [
        'user_id',
        'ride_id',
        'seats_booked',
        'total_price',
        'currency',
        'status',
        'pickup_address',
        'pickup_lat',
        'pickup_lng',
        'dropoff_address',
        'dropoff_lat',
        'dropoff_lng',
        'special_requests',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'seats_booked' => 'integer',
        'total_price' => 'decimal:2',
        'pickup_lat' => 'decimal:8',
        'pickup_lng' => 'decimal:8',
        'dropoff_lat' => 'decimal:8',
        'dropoff_lng' => 'decimal:8',
        'confirmed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    public function setStatusAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['status'] = null;

            return;
        }

        $normalized = strtolower(trim((string) $value));

        $this->attributes['status'] = self::STATUS_MAP[$normalized] ?? $normalized;
    }
}
