<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'trip_id',
        'user_id',
        'amount',
        'payer_phone',
        'transaction_reference',
        'screenshot_path',
        'verification_status',
        'verified_by',
        'verified_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'verified_at' => 'datetime',
    ];

    /**
     * Get the payment that owns the submission.
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * Get the trip associated with the submission.
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Get the user who submitted the payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the admin who verified the submission.
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Scope a query to only include pending submissions.
     */
    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    /**
     * Scope a query to only include approved submissions.
     */
    public function scopeApproved($query)
    {
        return $query->where('verification_status', 'approved');
    }

    /**
     * Scope a query to only include rejected submissions.
     */
    public function scopeRejected($query)
    {
        return $query->where('verification_status', 'rejected');
    }

    /**
     * Approve the payment submission.
     */
    public function approve(int $verifiedBy, ?string $notes = null): bool
    {
        $this->update([
            'verification_status' => 'approved',
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
            'notes' => $notes,
        ]);

        // Update payment status
        if ($this->payment) {
            $this->payment->update([
                'status' => 'paid',
                'verification_status' => 'verified',
                'paid_at' => now(),
            ]);

            // Fire PaymentVerified event
            event(new \App\Events\Domain\PaymentVerified($this->payment_id, $this->trip_id));
        }

        return true;
    }

    /**
     * Reject the payment submission.
     */
    public function reject(int $verifiedBy, string $notes): bool
    {
        $this->update([
            'verification_status' => 'rejected',
            'verified_by' => $verifiedBy,
            'verified_at' => now(),
            'notes' => $notes,
        ]);

        // Update payment status
        if ($this->payment) {
            $this->payment->update([
                'status' => 'failed',
                'verification_status' => 'rejected',
            ]);
        }

        return true;
    }
}
