<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RouteStop extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'stop_name',
        'stop_order',
        'lat',
        'lng',
    ];

    protected $casts = [
        'stop_order' => 'integer',
        'lat' => 'decimal:8',
        'lng' => 'decimal:8',
    ];

    public function route()
    {
        return $this->belongsTo(TransportRoute::class, 'route_id');
    }
}
