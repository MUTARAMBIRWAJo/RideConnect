<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'user_id',
        'driver_id',
        'ride_id',
        'rating',
        'comment',
        'safety_rating',
        'punctuality_rating',
        'communication_rating',
        'vehicle_condition_rating',
        'reviewer_type',
        'is_public',
    ];

    protected $casts = [
        'rating' => 'integer',
        'safety_rating' => 'integer',
        'punctuality_rating' => 'integer',
        'communication_rating' => 'integer',
        'vehicle_condition_rating' => 'integer',
        'is_public' => 'boolean',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}
