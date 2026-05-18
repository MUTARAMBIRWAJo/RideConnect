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
        'booking_id',
        'ride_id',
        'passenger_id',
        'driver_id',
        'transport_type',
        'route_state_id',
        'weather_condition_id',
        'pickup_location',
        'pickup_place_name',
        'pickup_lat',
        'pickup_lng',
        'pickup_zone',
        'dropoff_location',
        'dropoff_place_name',
        'dropoff_lat',
        'dropoff_lng',
        'dropoff_zone',
        'fare',
        'actual_pickup_lat',
        'actual_pickup_lng',
        'actual_dropoff_lat',
        'actual_dropoff_lng',
        'actual_distance',
        'actual_fare',
        'status',
        'payment_status',
        'assignment_status',
        'current_assignment_attempt_id',
        'requested_at',
        'accepted_at',
        'pickup_verified_at',
        'rejected_at',
        'rejection_reason',
        'started_at',
        'completed_at',
        'admin_completed_by',
        'admin_completion_reason',
        'paid_to_driver_at',
        'ranker_score',
        'ranker_version',
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'fare' => 'decimal:2',
        'actual_pickup_lat' => 'decimal:7',
        'actual_pickup_lng' => 'decimal:7',
        'actual_dropoff_lat' => 'decimal:7',
        'actual_dropoff_lng' => 'decimal:7',
        'actual_distance' => 'decimal:2',
        'actual_fare' => 'decimal:2',
        'requested_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'pickup_verified_at' => 'datetime',
        'paid_to_driver_at' => 'datetime',
        'ranker_score' => 'decimal:4',
    ];

    public function passenger()
    {
        return $this->belongsTo(MobileUser::class, 'passenger_id');
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class, 'ride_id');
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class, 'booking_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function routeState()
    {
        return $this->belongsTo(RouteState::class, 'route_state_id');
    }

    public function weatherCondition()
    {
        return $this->belongsTo(WeatherCondition::class, 'weather_condition_id');
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function transportTicket()
    {
        return $this->hasOne(TransportTicket::class);
    }

    public function assignmentAttempts()
    {
        return $this->hasMany(TripAssignmentAttempt::class);
    }

    public function currentAssignmentAttempt()
    {
        return $this->belongsTo(TripAssignmentAttempt::class, 'current_assignment_attempt_id');
    }

    public function seatReservations()
    {
        return $this->hasMany(SeatReservation::class);
    }

    public function publicBusBoarding()
    {
        return $this->hasOne(PassengerRouteBoarding::class, 'trip_id');
    }

    public function statusEvents()
    {
        return $this->hasMany(TripStatusEvent::class);
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

    protected static function booting(): void
    {
        static::creating(function (Trip $trip) {
            if (! $trip->pickup_location || ! $trip->dropoff_location) {
                throw new \InvalidArgumentException('Pickup and dropoff locations are required for all trips');
            }
        });
    }

    /**
     * Validate that trip has required fields for execution
     * Trip MUST always represent actual ride execution
     */
    public function validateForExecution(): void
    {
        if (! $this->pickup_location || ! $this->dropoff_location) {
            throw new \InvalidArgumentException('Trip must have valid pickup and dropoff locations');
        }

        if (! $this->passenger_id) {
            throw new \InvalidArgumentException('Trip must have a passenger');
        }
    }
}
