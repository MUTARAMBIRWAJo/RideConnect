<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandPrediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'zone_id',
        'zone_name',
        'lat',
        'lng',
        'intensity',
        'predicted_at',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'intensity' => 'float',
        'predicted_at' => 'datetime',
    ];
}
