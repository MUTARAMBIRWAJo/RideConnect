<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class FraudFlag extends Model
{
    protected $fillable = [
        'entity_type',
        'entity_id',
        'reason',
        'severity',
        'resolved',
        'resolved_by',
        'resolved_at',
        'metadata',
    ];

    protected $casts = [
        'resolved'    => 'boolean',
        'resolved_at' => 'datetime',
        'metadata'    => 'array',
    ];

    // -----------------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------------

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('resolved', false);
    }

    public function scopeHighSeverity(Builder $query): Builder
    {
        return $query->where('severity', 'high');
    }

    public function scopeForEntity(Builder $query, string $type, int $id): Builder
    {
        return $query->where('entity_type', $type)->where('entity_id', $id);
    }

    // -----------------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------------

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
