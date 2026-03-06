<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverWallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'total_earned',
        'total_paid',
        'total_commission_generated',
        'current_balance',
        'available_balance',
        'pending_balance',
        'frozen_balance',
    ];

    protected $casts = [
        'total_earned'               => 'decimal:2',
        'total_paid'                 => 'decimal:2',
        'total_commission_generated' => 'decimal:2',
        'current_balance'            => 'decimal:2',
        'available_balance'          => 'decimal:2',
        'pending_balance'            => 'decimal:2',
        'frozen_balance'             => 'decimal:2',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
