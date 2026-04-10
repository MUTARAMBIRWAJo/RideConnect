<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Zone extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
    ];

    public function startCorridors()
    {
        return $this->hasMany(Corridor::class, 'start_zone_id');
    }

    public function endCorridors()
    {
        return $this->hasMany(Corridor::class, 'end_zone_id');
    }

    public function rides()
    {
        return $this->hasMany(Ride::class);
    }
}
