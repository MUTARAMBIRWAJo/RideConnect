<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LedgerAccount extends Model
{
    protected $fillable = [
        'name',
        'type',
        'owner_type',
        'owner_id',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'owner_id'  => 'integer',
    ];

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'account_id');
    }

    /**
     * Normal balance (credit-normal for liability/revenue, debit-normal for asset/expense).
     */
    public function getRunningBalance(): float
    {
        $debit  = (float) $this->entries()->sum('debit');
        $credit = (float) $this->entries()->sum('credit');

        return match ($this->type) {
            'asset', 'expense'       => $debit - $credit,
            'liability', 'revenue'   => $credit - $debit,
            default                  => $debit - $credit,
        };
    }
}
