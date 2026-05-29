<?php

namespace App\Models;

class UserNotification extends Notification
{
    public function user()
    {
        return $this->belongsTo(MobileUser::class);
    }
}
