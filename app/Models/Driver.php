<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'license_number',
        'license_plate',
        'status',
        'availability_status',
        'current_latitude',
        'current_longitude',
        'last_online_at',
        'total_rides',
        'rating',
        'rating_count',
        'balance',
        'approved_at',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'balance' => 'decimal:2',
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
        'last_online_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_rides' => 'integer',
        'rating_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function wallet()
    {
        return $this->hasOne(DriverWallet::class);
    }

    public function payouts()
    {
        return $this->hasMany(DriverPayout::class);
    }

    public function commissions()
    {
        return $this->hasMany(PlatformCommission::class);
    }
}
