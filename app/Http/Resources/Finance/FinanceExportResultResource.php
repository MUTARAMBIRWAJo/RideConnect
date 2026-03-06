<?php

namespace App\Http\Resources\Finance;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinanceExportResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'filename' => $this['filename'] ?? null,
            'path' => $this['path'] ?? null,
            'url' => $this['url'] ?? null,
            'records' => $this['records'] ?? 0,
            'type' => $this['type'] ?? null,
            'format' => $this['format'] ?? null,
            'generated_at' => $this['generated_at'] ?? now()->toIso8601String(),
        ];
    }
}
