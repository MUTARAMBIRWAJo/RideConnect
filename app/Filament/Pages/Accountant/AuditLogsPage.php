<?php

namespace App\Filament\Pages\Accountant;

use App\Enums\UserRole;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditLogsPage extends Page
{
    protected static ?string $navigationGroup = 'Compliance';

    protected static string $view = 'filament.pages.accountant.audit-logs';

    /** @var array<int, array<string, mixed>> */
    public array $auditLogs = [];

    public int $totalAuditEntries = 0;

    public int $suspiciousTransactions = 0;

    public static function getNavigationLabel(): string
    {
        return 'Audit Logs';
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-list-bullet';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return ($user->role === UserRole::ACCOUNTANT)
            || $user->hasAnyRole(['Accountant', 'accountant', 'ACCOUNTANT']);
    }

    public function getTitle(): string
    {
        return 'Financial Audit Logs & Compliance';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadAuditLogs();
    }

    private function loadAuditLogs(): void
    {
        if (Schema::hasTable('activity_logs')) {
            $query = DB::table('activity_logs');
            $actorExpression = DB::raw("'System' as actor");

            if (Schema::hasTable('managers') && Schema::hasColumn('activity_logs', 'manager_id')) {
                $query->leftJoin('managers', 'managers.id', '=', 'activity_logs.manager_id');
                $actorExpression = DB::raw("COALESCE(managers.name, 'System') as actor");
            }

            $this->auditLogs = $query
                ->select([
                    'activity_logs.id',
                    DB::raw("COALESCE(activity_logs.action, 'system.event') as action"),
                    DB::raw("COALESCE(activity_logs.description, '') as description"),
                    DB::raw('COALESCE(activity_logs.created_at, CURRENT_TIMESTAMP) as created_at'),
                    $actorExpression,
                ])
                ->latest('activity_logs.id')
                ->limit(50)
                ->get()
                ->map(function ($row): array {
                    $item = (array) $row;
                    $action = strtolower((string) ($item['action'] ?? ''));

                    return [
                        'id' => $item['id'] ?? null,
                        'ride_id' => null,
                        'fare_difference' => 0,
                        'status' => str_contains($action, 'reject') || str_contains($action, 'cancel') ? 'Suspicious' : 'Valid',
                        'actor' => $item['actor'] ?? 'System',
                        'created_at' => $item['created_at'] ?? now()->toDateTimeString(),
                    ];
                })
                ->all();

            $this->totalAuditEntries = count($this->auditLogs);
            $this->suspiciousTransactions = collect($this->auditLogs)->filter(fn ($log) => ($log['status'] ?? '') === 'Suspicious')->count();

            return;
        }

        $this->auditLogs = [];
        $this->totalAuditEntries = 0;
        $this->suspiciousTransactions = 0;
    }
}
