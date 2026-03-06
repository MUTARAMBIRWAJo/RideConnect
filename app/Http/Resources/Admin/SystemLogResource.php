<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? null,
            'manager_id' => $this->manager_id ?? null,
            'action' => $this->action ?? null,
            'description' => $this->description ?? null,
            'created_at' => $this->created_at ?? null,
        ];
    }
}
