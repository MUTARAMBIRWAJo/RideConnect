<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RuraTariff extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_code',
        'corridor',
        'origin_stop',
        'destination_stop',
        'fare_rwf',
    ];
}
