<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassengerBehavior extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'passenger_id',
        'trip_id',
        'rating',
        'reliability_score',
        'cancellation_rate',
        'no_show_rate',
        'payment_reliability',
        'total_trips',
        'notes',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'reliability_score' => 'decimal:4',
        'cancellation_rate' => 'decimal:4',
        'no_show_rate' => 'decimal:4',
        'payment_reliability' => 'decimal:4',
        'total_trips' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function passenger()
    {
        return $this->belongsTo(MobileUser::class, 'passenger_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
