<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationHistory extends Model
{
    use HasFactory;

    protected $table = 'location_histories';

    // Disable standard updated_at/created_at since we only have created_at
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'role',
        'trip_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'created_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'trip_id' => 'integer',
        'latitude' => 'double',
        'longitude' => 'double',
        'speed' => 'double',
        'heading' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
