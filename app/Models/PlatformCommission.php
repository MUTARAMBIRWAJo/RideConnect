<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'ride_id',
        'commission_amount',
        'date',
    ];

    protected $casts = [
        'commission_amount' => 'decimal:2',
        'date' => 'date',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function ride()
    {
        return $this->belongsTo(Ride::class);
    }
}
