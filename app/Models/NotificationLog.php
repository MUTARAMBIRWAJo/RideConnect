<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'title',
        'body',
        'payload',
        'status',
        'failure_reason',
        'message_id',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * Get the parent recipient model (User, Driver, etc.).
     */
    public function recipient(): MorphTo
    {
        return $this->morphTo();
    }
}
