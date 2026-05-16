<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'make',
        'model',
        'year',
        'color',
        'vehicle_type',
        'seats',
        'air_conditioning',
        'is_active',
        'photo_url',
        'verified_at',
    ];

    protected $casts = [
        'air_conditioning' => 'boolean',
        'is_active' => 'boolean',
        'verified_at' => 'datetime',
        'seats' => 'integer',
        'year' => 'integer',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }
}
