<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_name',
        'percentage',
        'applies_to',
        'jurisdiction',
        'active',
        'effective_from',
        'effective_until',
        'description',
        'created_by',
    ];

    protected $casts = [
        'percentage'     => 'float',
        'active'         => 'boolean',
        'effective_from' => 'date',
        'effective_until'=> 'date',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('active', true)
            ->where(fn ($q) => $q
                ->whereNull('effective_from')
                ->orWhere('effective_from', '<=', now()->toDateString())
            )
            ->where(fn ($q) => $q
                ->whereNull('effective_until')
                ->orWhere('effective_until', '>=', now()->toDateString())
            );
    }

    public function scopeForJurisdiction(\Illuminate\Database\Eloquent\Builder $query, string $code): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('jurisdiction', $code);
    }

    public function scopeAppliesTo(\Illuminate\Database\Eloquent\Builder $query, string $type): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where(fn ($q) => $q
            ->where('applies_to', $type)
            ->orWhere('applies_to', 'all')
        );
    }

    public function getDecimalRateAttribute(): float
    {
        return $this->percentage / 100;
    }
}
