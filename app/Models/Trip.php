<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    private const STATUS_MAP = [
        'pending' => 'requested',
        'requested' => 'requested',
        'assigning' => 'assigning',
        'accepted' => 'accepted',
        'started' => 'in_progress',
        'enroute_to_pickup' => 'enroute_to_pickup',
        'arrived_at_pickup' => 'arrived_at_pickup',
        'in_progress' => 'in_progress',
        'completed' => 'completed',
        'cancelled' => 'cancelled',
    ];

    protected $fillable = [
        'passenger_id',
        'driver_id',
        'pickup_location',
        'dropoff_location',
        'pickup_lat',
        'pickup_lng',
        'dropoff_lat',
        'dropoff_lng',
        'pickup_zone',
        'dropoff_zone',
        'pickup_place_name',
        'dropoff_place_name',
        'fare',
        'actual_fare',
        'actual_distance',
        'actual_pickup_lat',
        'actual_pickup_lng',
        'actual_dropoff_lat',
        'actual_dropoff_lng',
        'status',
        'payment_status',
        'assignment_status',
        'transport_type',
        'matching_session_id',
        'idempotency_key',
        'ride_id',
        'booking_id',
        'driver_behavior_id',
        'passenger_behavior_id',
        'route_state_id',
        'weather_condition_id',
        'current_assignment_attempt_id',
        'trip_quality_score',
        'eta_deviation_minutes',
        'rejected_drivers_count',
        'ranker_score',
        'ranker_version',
        'admin_completed_by',
        'admin_completion_reason',
        'requested_at',
        'accepted_at',
        'started_at',
        'completed_at',
        'rejected_at',
        'rejection_reason',
        'paid_to_driver_at',
        'pickup_verified_at',
    ];

    protected $casts = [
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'fare' => 'decimal:2',
        'actual_fare' => 'decimal:2',
        'actual_distance' => 'decimal:2',
        'actual_pickup_lat' => 'decimal:7',
        'actual_pickup_lng' => 'decimal:7',
        'actual_dropoff_lat' => 'decimal:7',
        'actual_dropoff_lng' => 'decimal:7',
        'ranker_score' => 'decimal:4',
        'trip_quality_score' => 'decimal:4',
        'rejected_drivers_count' => 'integer',
        'eta_deviation_minutes' => 'integer',
        'requested_at' => 'datetime',
        'accepted_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'rejected_at' => 'datetime',
        'pickup_verified_at' => 'datetime',
        'paid_to_driver_at' => 'datetime',
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

    public function matchingSession()
    {
        return $this->belongsTo(MatchingSession::class, 'matching_session_id', 'matching_session_id');
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

        $this->attributes['status'] = self::STATUS_MAP[$normalized] ?? $normalized;
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
