<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReconciliationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'reconciliation_id',
        'payment_provider',
        'reconciliation_date',
        'payment_id',
        'provider_transaction_id',
        'expected_amount',
        'actual_amount',
        'currency',
        'status',
        'discrepancy_amount',
        'discrepancy_reason',
        'provider_data',
        'system_data',
        'reconciled_at',
        'reconciliation_started_at',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'discrepancy_amount' => 'decimal:2',
        'provider_data' => 'array',
        'system_data' => 'array',
        'reconciled_at' => 'datetime',
        'reconciliation_started_at' => 'datetime',
        'reconciliation_date' => 'date',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
