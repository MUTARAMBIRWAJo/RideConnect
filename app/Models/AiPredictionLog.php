<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiPredictionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'prediction_type',
        'trip_id',
        'request_payload',
        'response_payload',
        'response_time_ms',
        'success',
        'requested_at',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'success' => 'boolean',
        'requested_at' => 'datetime',
    ];
}
