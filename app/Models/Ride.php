<?php

namespace App\Models;

use App\Domain\Ride\RidePolicy;
use App\Services\TransportMappingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ride extends Model
{
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ride) {
            if (!$ride->ride_type) {
                $ride->ride_type = self::TYPE_LOCAL;
            }
        });

        static::saving(function ($ride) {
            $ride->validateTransportRules();
        });
    }

    // Transport type constants
    const TRANSPORT_BUS = 'BUS';
    const TRANSPORT_CAR = 'CAR';
    const TRANSPORT_MOTORCYCLE = 'MOTORCYCLE';

    // Travel mode constants
    const MODE_SCHEDULED = 'SCHEDULED';
    const MODE_ON_DEMAND = 'ON_DEMAND';

    // Ride type constants
    const TYPE_INTERCITY = 'INTERCITY';
    const TYPE_LOCAL = 'LOCAL';

    private const STATUS_MAP = [
        'scheduled' => 'published',
        'active' => 'published',
        'available' => 'published',
        'draft' => 'draft',
        'published' => 'published',
        'in_progress' => 'in_progress',
        'started' => 'in_progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
    ];

    protected $fillable = [
        'driver_id',
        'zone_id',
        'corridor_id',
        'route_id',
        'created_by',
        'vehicle_id',
        'transport_type',
        'travel_mode',
        'ride_type',
        'origin_address',
        'origin_lat',
        'origin_lng',
        'destination_address',
        'destination_lat',
        'destination_lng',
        'departure_time',
        'arrival_time_estimated',
        'available_seats',
        'price_per_seat',
        'currency',
        'description',
        'status',
        'bus_number',
        'luggage_allowed',
        'pets_allowed',
        'smoking_allowed',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'origin_lat' => 'decimal:8',
        'origin_lng' => 'decimal:8',
        'destination_lat' => 'decimal:8',
        'destination_lng' => 'decimal:8',
        'departure_time' => 'datetime',
        'arrival_time_estimated' => 'datetime',
        'price_per_seat' => 'decimal:2',
        'available_seats' => 'integer',
        'luggage_allowed' => 'boolean',
        'pets_allowed' => 'boolean',
        'smoking_allowed' => 'boolean',
        'cancelled_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function corridor()
    {
        return $this->belongsTo(Corridor::class);
    }

    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Transport classification helper methods
    public function isScheduled(): bool
    {
        return $this->travel_mode === self::MODE_SCHEDULED;
    }

    public function isOnDemand(): bool
    {
        return $this->travel_mode === self::MODE_ON_DEMAND;
    }

    public function isBus(): bool
    {
        return $this->transport_type === self::TRANSPORT_BUS;
    }

    public function isCar(): bool
    {
        return $this->transport_type === self::TRANSPORT_CAR;
    }

    public function isMotorcycle(): bool
    {
        return $this->transport_type === self::TRANSPORT_MOTORCYCLE;
    }

    /**
     * Check if a vehicle type is compatible with this ride's transport type.
     *
     * @param string|null $vehicleType The vehicle type to check (e.g., 'sedan', 'van', 'motorbike')
     * @return bool
     */
    public function isVehicleCompatible(?string $vehicleType): bool
    {
        return TransportMappingService::isCompatible($vehicleType, $this->transport_type);
    }

    // Ride type classification helpers
    public function isIntercity(): bool
    {
        return $this->ride_type === self::TYPE_INTERCITY;
    }

    public function isLocal(): bool
    {
        return $this->ride_type === self::TYPE_LOCAL;
    }

    /**
     * Validate transport type and travel mode combinations
     */
    public function validateTransportRules(): void
    {
        if (!$this->ride_type) {
            $this->ride_type = self::TYPE_LOCAL;
        }

        // Validate ride_type is valid
        if (!in_array($this->ride_type, [self::TYPE_INTERCITY, self::TYPE_LOCAL], true)) {
            throw new \InvalidArgumentException("Invalid ride type: {$this->ride_type}");
        }

        // BUS must be SCHEDULED
        if ($this->isBus() && ! $this->isScheduled()) {
            throw new \InvalidArgumentException("BUS must be SCHEDULED");
        }

        if ($this->isBus()) {
            try {
                RidePolicy::assertBusRules($this);
            } catch (\Throwable $e) {
                throw new \InvalidArgumentException($e->getMessage(), 0, $e);
            }
        }

        // MOTORCYCLE must be ON_DEMAND
        if ($this->isMotorcycle() && ! $this->isOnDemand()) {
            throw new \InvalidArgumentException("MOTORCYCLE must be ON_DEMAND");
        }

        // CAR can be either SCHEDULED or ON_DEMAND - nothing to do
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

    public function getStatusAttribute($value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtoupper((string) $value);
    }

    public function setRideTypeAttribute($value): void
    {
        if ($value === null || !in_array($value, [self::TYPE_INTERCITY, self::TYPE_LOCAL], true)) {
            $this->attributes['ride_type'] = self::TYPE_LOCAL;
        } else {
            $this->attributes['ride_type'] = $value;
        }
    }
}
