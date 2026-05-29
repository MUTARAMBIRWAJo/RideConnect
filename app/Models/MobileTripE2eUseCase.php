<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MobileTripE2eUseCase extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'transport_type',
        'passenger_page',
        'passenger_flow',
        'driver_flow',
        'api_payloads',
        'api_responses',
        'expected_ui',
        'notifications',
        'matching_engine_results',
        'tracking_updates',
        'backend_validation',
        'database_validation',
        'failure_simulations',
        'correction_prompts',
        'pass_fail_validations',
        'is_active',
    ];

    protected $casts = [
        'passenger_flow' => 'array',
        'driver_flow' => 'array',
        'api_payloads' => 'array',
        'api_responses' => 'array',
        'expected_ui' => 'array',
        'notifications' => 'array',
        'matching_engine_results' => 'array',
        'tracking_updates' => 'array',
        'backend_validation' => 'array',
        'database_validation' => 'array',
        'failure_simulations' => 'array',
        'correction_prompts' => 'array',
        'pass_fail_validations' => 'array',
        'is_active' => 'boolean',
    ];
}
