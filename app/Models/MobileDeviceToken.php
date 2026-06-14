<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MobileDeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'platform',
        'device_token',
        'device_id',
        'last_seen_at',
        'is_active',
        'last_used_at',
        'app_version',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
