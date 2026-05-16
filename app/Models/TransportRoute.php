<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransportRoute extends Model
{
    use HasFactory;

    protected $table = 'routes';

    protected $fillable = [
        'corridor_id',
        'route_code',
        'name',
        'via',
        'origin',
        'destination',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function corridor()
    {
        return $this->belongsTo(Corridor::class);
    }

    public function routeStops()
    {
        return $this->hasMany(RouteStop::class, 'route_id')->orderBy('stop_order');
    }

    public function rides()
    {
        return $this->hasMany(Ride::class, 'route_id');
    }
}
