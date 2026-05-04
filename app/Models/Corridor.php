<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Corridor extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'kinyarwanda_name',
        'start_zone_id',
        'end_zone_id',
        'base_fare',
        'price_per_km',
    ];

    protected $casts = [
        'base_fare' => 'decimal:2',
        'price_per_km' => 'decimal:2',
    ];

    public function startZone()
    {
        return $this->belongsTo(Zone::class, 'start_zone_id');
    }

    public function endZone()
    {
        return $this->belongsTo(Zone::class, 'end_zone_id');
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }

    public function routes()
    {
        return $this->hasMany(TransportRoute::class);
    }
}
