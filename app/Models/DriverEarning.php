<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverEarning extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'driver_id',
        'trip_id',
        'amount',
        'commission',
        'net_amount',
        'created_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'commission' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
