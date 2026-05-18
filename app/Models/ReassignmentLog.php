<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReassignmentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'old_driver_id',
        'new_driver_id',
        'reason',
        'triggered_by',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function oldDriver()
    {
        return $this->belongsTo(Driver::class, 'old_driver_id');
    }

    public function newDriver()
    {
        return $this->belongsTo(Driver::class, 'new_driver_id');
    }
}
