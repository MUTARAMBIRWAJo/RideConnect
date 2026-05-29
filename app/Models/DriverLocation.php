<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'heading',
        'accuracy',
        'last_activity_at',
        'is_online',
    ];

    public $timestamps = false;

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'speed_kmh' => 'decimal:2',
        'heading' => 'decimal:1',
        'accuracy' => 'decimal:2',
        'updated_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'is_online' => 'boolean',
    ];

    public function driver()
    {
        return $this->belongsTo(MobileUser::class, 'driver_id');
    }
}
