<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventOutbox extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'aggregate_id',
        'aggregate_type',
        'payload',
        'version',
        'occurred_at',
        'status',
        'attempts',
        'published_at',
        'last_error',
        'topic',
    ];

    protected $casts = [
        'payload'      => 'array',
        'occurred_at'  => 'datetime',
        'published_at' => 'datetime',
        'attempts'     => 'integer',
        'version'      => 'integer',
    ];

    public function toEventArray(): array
    {
        return [
            'event_id'       => $this->event_id,
            'event_type'     => $this->event_type,
            'aggregate_id'   => $this->aggregate_id,
            'aggregate_type' => $this->aggregate_type,
            'payload'        => $this->payload,
            'version'        => $this->version,
            'occurred_at'    => $this->occurred_at?->toIso8601String(),
            'topic'          => $this->topic,
        ];
    }

    public function scopePending(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'pending')->where('attempts', '<', 5);
    }
}
