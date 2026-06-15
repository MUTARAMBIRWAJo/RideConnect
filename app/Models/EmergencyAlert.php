<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Services\Firebase\RealtimeDatabaseManager;
use Illuminate\Support\Facades\Log;

class EmergencyAlert extends Model
{
    protected $fillable = [
        'emergency_report_id',
        'severity',
        'status',
        'message',
    ];

    /**
     * Get the parent emergency report.
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(EmergencyReport::class, 'emergency_report_id');
    }

    /**
     * Boot model events to mirror writes to Firebase RTDB.
     */
    protected static function booted(): void
    {
        static::saved(function (self $alert) {
            try {
                $rtdb = app(RealtimeDatabaseManager::class);
                $rtdb->set("emergency_alerts/{$alert->id}", [
                    'id' => $alert->id,
                    'emergency_report_id' => $alert->emergency_report_id,
                    'severity' => $alert->severity,
                    'status' => $alert->status,
                    'message' => $alert->message,
                    'updated_at' => $alert->updated_at?->toIso8601String() ?? now()->toIso8601String(),
                ]);
            } catch (\Throwable $e) {
                Log::error('[EmergencyAlert] Failed to mirror alert to Firebase RTDB', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::deleted(function (self $alert) {
            try {
                $rtdb = app(RealtimeDatabaseManager::class);
                $rtdb->delete("emergency_alerts/{$alert->id}");
            } catch (\Throwable $e) {
                Log::error('[EmergencyAlert] Failed to delete alert mirror from Firebase RTDB', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
