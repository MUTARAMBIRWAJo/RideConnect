<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_type',
        'period_start',
        'period_end',
        'generated_by',
        'file_path',
        'format',
        'status',
        'summary_data',
        'metadata',
        'error_message',
        'generated_at',
    ];

    protected $casts = [
        'period_start'  => 'date',
        'period_end'    => 'date',
        'summary_data'  => 'array',
        'metadata'      => 'array',
        'generated_at'  => 'datetime',
    ];

    public const TYPES = [
        'daily_ride_summary'    => 'Daily Ride Summary',
        'driver_earnings'       => 'Driver Earnings Summary',
        'commission_breakdown'  => 'Commission Breakdown',
        'tax_payable_summary'   => 'Tax Payable Summary',
        'refund_report'         => 'Refund Report',
        'fraud_incident_report' => 'Fraud Incident Report',
    ];

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function scopeReady(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'ready');
    }

    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    public function hasFile(): bool
    {
        return $this->file_path && \Illuminate\Support\Facades\Storage::exists($this->file_path);
    }
}
