<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'notification_id',
        'user_id',
        'channel',
        'status',
        'delivered_at',
        'acknowledged_at',
        'payload',
        'metadata',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'payload' => 'array',
        'metadata' => 'array',
    ];

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
