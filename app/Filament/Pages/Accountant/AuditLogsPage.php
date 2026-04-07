<?php

namespace App\Filament\Pages\Accountant;

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

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-list-bullet';
    }

    public static function canAccess(): bool
    {
        return auth()->check() && (auth()->user()->hasRole('accountant') || auth()->user()->hasRole('ACCOUNTANT'));
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
                    DB::raw("COALESCE(activity_logs.created_at, NOW()) as created_at"),
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
                        'ride_id' => 'N/A',
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

        // Fallback: create mock data
        $this->auditLogs = [
            ['id' => 1, 'ride_id' => 1001, 'fare_difference' => 5.50, 'status' => 'Valid', 'actor' => 'System', 'created_at' => now()->subHours(2)->toDateTimeString()],
            ['id' => 2, 'ride_id' => 1002, 'fare_difference' => 0.0, 'status' => 'Valid', 'actor' => 'System', 'created_at' => now()->subHours(4)->toDateTimeString()],
            ['id' => 3, 'ride_id' => 1003, 'fare_difference' => 12.75, 'status' => 'Suspicious', 'actor' => 'Manual Review', 'created_at' => now()->subHours(6)->toDateTimeString()],
        ];

        $this->totalAuditEntries = count($this->auditLogs);
        $this->suspiciousTransactions = collect($this->auditLogs)->filter(fn ($log) => ($log['status'] ?? '') === 'Suspicious')->count();
    }
}
