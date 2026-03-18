<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AIModelMetric extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_name',
        'metric_name',
        'metric_value',
        'evaluated_at',
    ];

    protected $casts = [
        'metric_value' => 'float',
        'evaluated_at' => 'datetime',
    ];
}
