<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this['type'] ?? null,
            'id' => $this['id'] ?? null,
            'status' => $this['status'] ?? null,
            'passenger' => $this['passenger'] ?? null,
            'driver' => $this['driver'] ?? null,
            'fare' => $this['fare'] ?? null,
            'user' => $this['user'] ?? null,
            'amount' => $this['amount'] ?? null,
            'created_at' => $this['created_at'] ?? null,
        ];
    }
}
