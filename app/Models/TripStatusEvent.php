<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripStatusEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trip_id',
        'actor_type',
        'actor_id',
        'old_status',
        'new_status',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
