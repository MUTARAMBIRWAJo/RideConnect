<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RideCancellation extends Model
{
    use HasFactory;

    protected $table = 'ride_cancellations';

    protected $fillable = [
        'trip_id',
        'ride_id',
        'driver_id',
        'passenger_id',
        'reason',
        'cancelled_at',
        'cancellation_fee',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
        'cancellation_fee' => 'decimal:2',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class, 'trip_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function passenger()
    {
        return $this->belongsTo(MobileUser::class, 'passenger_id');
    }
}
