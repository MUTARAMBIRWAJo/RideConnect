<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRoleUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value ?? $this->role,
            'spatie_roles' => method_exists($this, 'getRoleNames') ? $this->getRoleNames()->values()->all() : [],
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
