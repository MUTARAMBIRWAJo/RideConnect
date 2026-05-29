<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TripRejection extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'driver_id',
        'reason',
    ];
}
