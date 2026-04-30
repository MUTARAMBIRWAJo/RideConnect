<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * IMPORTANT: SUPPORT TICKETS MODEL
 *
 * This model represents SUPPORT TICKETS (complaints/issues)
 * It is NOT a transport ticket (seat ticket).
 *
 * Do NOT use for pricing or seat allocation.
 * This is for customer support tracking only.
 */
class Ticket extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'trip_id',
        'issued_by',
        'reason',
        'amount',
        'status',
        'issued_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'issued_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function issuer()
    {
        return $this->belongsTo(Manager::class, 'issued_by');
    }
}
