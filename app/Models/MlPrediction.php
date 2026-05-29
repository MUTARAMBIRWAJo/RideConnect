<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MlPrediction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trip_id',
        'model_name',
        'model_version',
        'endpoint',
        'input_payload',
        'output_payload',
        'latency_ms',
        'created_at',
    ];

    protected $casts = [
        'input_payload' => 'array',
        'output_payload' => 'array',
        'created_at' => 'datetime',
    ];
}
