<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CorridorStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'corridor_id',
        'stop_name',
        'stop_order',
        'latitude',
        'longitude',
        'is_major_terminal',
        'status',
    ];

    protected $casts = [
        'stop_order' => 'integer',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_major_terminal' => 'boolean',
    ];

    public function corridor()
    {
        return $this->belongsTo(TransportCorridor::class, 'corridor_id');
    }

    public function stopTimes()
    {
        return $this->hasMany(CorridorStopTime::class, 'corridor_stop_id');
    }
}