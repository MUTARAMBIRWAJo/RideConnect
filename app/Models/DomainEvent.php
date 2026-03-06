<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DomainEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'aggregate_id',
        'aggregate_type',
        'payload',
        'version',
        'occurred_at',
        'processed',
        'processed_at',
        'processor_id',
        'retry_count',
        'last_error',
        'payload_hash',
    ];

    protected $casts = [
        'payload'      => 'array',
        'occurred_at'  => 'datetime',
        'processed'    => 'boolean',
        'processed_at' => 'datetime',
        'version'      => 'integer',
        'retry_count'  => 'integer',
    ];

    // Domain events are append-only; prevent mutation of core fields
    public function update(array $attributes = [], array $options = []): bool
    {
        $mutable = ['processed', 'processed_at', 'processor_id', 'retry_count', 'last_error'];
        $keys    = array_keys($attributes);

        foreach ($keys as $key) {
            if (! in_array($key, $mutable, true)) {
                throw new \RuntimeException("domain_events field '{$key}' is immutable.");
            }
        }

        return parent::update($attributes, $options);
    }

    public function delete(): bool|null
    {
        throw new \RuntimeException('domain_events rows are immutable and cannot be deleted.');
    }

    // Scopes
    public function scopeUnprocessed(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('processed', false);
    }

    public function scopeForAggregate(\Illuminate\Database\Eloquent\Builder $query, string $type, string $id): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('aggregate_type', $type)->where('aggregate_id', $id);
    }
}
