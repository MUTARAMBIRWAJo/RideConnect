<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmergencyReport extends Model
{
    protected $fillable = [
        'user_id',
        'trip_id',
        'latitude',
        'longitude',
        'status',
        'details',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(EmergencyAlert::class);
    }
}
