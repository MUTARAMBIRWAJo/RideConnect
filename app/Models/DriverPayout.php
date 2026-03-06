<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'payout_date',
        'total_income',
        'commission_amount',
        'payout_amount',
        'processed_by',
        'status',
        'processed_at',
    ];

    protected $casts = [
        'payout_date' => 'date',
        'total_income' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'payout_amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
