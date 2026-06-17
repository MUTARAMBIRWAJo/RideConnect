<?php

namespace App\Models\V3;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class TripMessageV3 extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'trip_messages_v3';

    protected $fillable = [
        'trip_id',
        'sender_id',
        'message',
    ];

    public function trip()
    {
        return $this->belongsTo(TripV3::class, 'trip_id');
    }

    public function sender()
    {
        return $this->belongsTo(\App\Models\User::class, 'sender_id');
    }
}
