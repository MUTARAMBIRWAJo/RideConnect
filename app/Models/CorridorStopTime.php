<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorridorStopTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'corridor_id',
        'corridor_stop_id',
        'scheduled_arrival_time',
        'scheduled_departure_time',
        'service_day_of_week',
        'service_date',
        'status',
    ];

    protected $casts = [
        'service_day_of_week' => 'integer',
        'service_date' => 'date',
    ];

    public function corridor()
    {
        return $this->belongsTo(TransportCorridor::class, 'corridor_id');
    }

    public function stop()
    {
        return $this->belongsTo(CorridorStop::class, 'corridor_stop_id');
    }
}