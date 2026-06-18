<?php

namespace App\Models;

use App\Models\TripAssignmentAttempt;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Driver extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'license_number',
        'license_plate',
        'status',
        'availability_status',
        'is_active',
        'is_available',
        'current_trip_id',
        'is_test',
        'current_latitude',
        'current_longitude',
        'last_location_lat',
        'last_location_lng',
        'last_online_at',
        'online_since',
        'total_rides',
        'rating',
        'rating_count',
        'balance',
        'approved_at',
        'last_seen_at',
        'is_online',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'balance' => 'decimal:2',
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
        'last_online_at' => 'datetime',
        'approved_at' => 'datetime',
        'online_since' => 'datetime',
        'total_rides' => 'integer',
        'rating_count' => 'integer',
        'is_test' => 'boolean',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'last_seen_at' => 'datetime',
        'is_online' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @deprecated Legacy bridge — drivers.user_id references users.id via user().
     */
    public function mobileUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function assignmentAttempts()
    {
        return $this->hasMany(TripAssignmentAttempt::class);
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    public function motorcycleTrips(): HasMany
    {
        return $this->hasMany(MotorcycleTrip::class, 'driver_id');
    }

    public function hasActiveMotoTrip(): bool
    {
        return $this->motorcycleTrips()
            ->whereIn('status', ['ASSIGNED', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING', 'IN_PROGRESS'])
            ->exists();
    }

    public function wallet()
    {
        return $this->hasOne(DriverWallet::class, 'driver_id');
    }

    public function earningsRecords()
    {
        return $this->hasMany(DriverEarning::class, 'driver_id');
    }

    public function behaviors()
    {
        return $this->hasMany(DriverBehavior::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->hasOne(Vehicle::class, 'driver_id')
            ->where('is_active', true);
    }

    public function payouts()
    {
        return $this->hasMany(DriverPayout::class);
    }

    public function commissions()
    {
        return $this->hasMany(PlatformCommission::class);
    }

    public function availabilitySnapshots()
    {
        return $this->hasMany(DriverAvailabilitySnapshot::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Driver $driver): void {
            if (Str::startsWith((string) $driver->license_number, 'TEST_RANKER_')) {
                $driver->is_test = true;
            }
        });
    }
}
