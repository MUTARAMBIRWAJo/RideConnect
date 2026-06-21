<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassengerLocation extends Model
{
    use HasFactory;

    protected $table = 'passenger_locations';

    protected $fillable = [
        'user_id',
        'trip_id',
        'lat',
        'lng',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'accuracy',
        'is_online',
        'recorded_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'trip_id' => 'integer',
        'lat' => 'double',
        'lng' => 'double',
        'latitude' => 'double',
        'longitude' => 'double',
        'speed' => 'double',
        'heading' => 'integer',
        'accuracy' => 'double',
        'is_online' => 'boolean',
        'recorded_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
