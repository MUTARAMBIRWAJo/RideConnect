<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportCorridor extends Model
{
    use HasFactory;

    protected $fillable = [
        'corridor_code',
        'corridor_name',
        'start_stop_id',
        'end_stop_id',
        'transport_type',
        'status',
        'estimated_duration_minutes',
    ];

    protected $casts = [
        'estimated_duration_minutes' => 'integer',
    ];

    public function stops()
    {
        return $this->hasMany(CorridorStop::class, 'corridor_id')->orderBy('stop_order');
    }

    public function stopTimes()
    {
        return $this->hasMany(CorridorStopTime::class, 'corridor_id');
    }

    public function busRouteAssignments()
    {
        return $this->hasMany(BusRouteAssignment::class, 'corridor_id');
    }

    public function passengerBoardings()
    {
        return $this->hasMany(PassengerRouteBoarding::class, 'corridor_id');
    }
}