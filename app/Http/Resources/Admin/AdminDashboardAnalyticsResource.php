<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardAnalyticsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'period' => $this['period'] ?? null,
            'users' => $this['users'] ?? [],
            'trips' => $this['trips'] ?? [],
            'rides' => $this['rides'] ?? [],
            'bookings' => $this['bookings'] ?? [],
            'revenue' => $this['revenue'] ?? [],
        ];
    }
}
