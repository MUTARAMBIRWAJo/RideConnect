<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class LedgerEntry extends Model
{
    // Entries have created_at only — no updated_at (enforced by DB trigger too)
    const UPDATED_AT = null;

    protected $fillable = [
        'account_id',
        'transaction_id',
        'debit',
        'credit',
        'reference_type',
        'reference_id',
        'description',
    ];

    protected $casts = [
        'debit'      => 'decimal:2',
        'credit'     => 'decimal:2',
        'created_at' => 'datetime',
    ];

    // -----------------------------------------------------------------------
    // Immutability guards (belt + suspenders alongside the DB trigger)
    // -----------------------------------------------------------------------

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new RuntimeException('Ledger entries are immutable and cannot be updated.');
    }

    public function delete(): bool|null
    {
        throw new RuntimeException('Ledger entries are immutable and cannot be deleted.');
    }

    // -----------------------------------------------------------------------
    // Relations
    // -----------------------------------------------------------------------

    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'account_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(LedgerTransaction::class, 'transaction_id');
    }
}
