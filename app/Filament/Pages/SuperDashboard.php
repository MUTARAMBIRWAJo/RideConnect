<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\SuperAdmin\AiInsightsWidget;
use App\Filament\Widgets\SuperAdmin\BookingTripRatioChartWidget;
use App\Filament\Widgets\SuperAdmin\DriverActivityChartWidget;
use App\Filament\Widgets\SuperAdmin\KpiOverviewWidget;
use App\Filament\Widgets\SuperAdmin\LiveRideMapWidget;
use App\Filament\Widgets\SuperAdmin\RecentActivityWidget;
use App\Filament\Widgets\SuperAdmin\RevenueTrendChartWidget;
use App\Filament\Widgets\SuperAdmin\RidesPerHourChartWidget;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuperDashboard extends BaseDashboard
{
    protected static string $routePath = '/super-dashboard';

    protected static string $view = 'filament.pages.super-dashboard';

    /** @var array<string, int> */
    public array $operationsSnapshot = [
        'pending_drivers' => 0,
        'pending_users' => 0,
        'failed_payments_24h' => 0,
        'pending_outbox' => 0,
    ];

    public ?string $lastMaintenanceActionAt = null;

    public string $driverApprovalReason = '';

    public string $userApprovalReason = '';

    public int $driverApprovalPreview = 0;

    public int $userApprovalPreview = 0;

    protected static function dashboardRole(): UserRole
    {
        return UserRole::SUPER_ADMIN;
    }

    public static function getNavigationLabel(): string
    {
        return 'Super Dashboard';
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-shield-check';
    }

    public function mount(): void
    {
        parent::mount();
        $this->refreshOperationsSnapshot();
    }

    public function getWidgets(): array
    {
        // Keep this dashboard deterministic and avoid role-configured widget side effects.
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        if ((bool) config('dashboard.super_dashboard_static_mode', false)) {
            return [];
        }

        return [
            KpiOverviewWidget::class,
            LiveRideMapWidget::class,
            AiInsightsWidget::class,
            RidesPerHourChartWidget::class,
            RevenueTrendChartWidget::class,
            DriverActivityChartWidget::class,
            BookingTripRatioChartWidget::class,
            RecentActivityWidget::class,
        ];
    }

    public function clearApplicationCaches(): void
    {
        if (! static::canAccess()) {
            abort(403);
        }

        Artisan::call('optimize:clear');
        $this->lastMaintenanceActionAt = now()->toDateTimeString();
        $this->refreshOperationsSnapshot();
        $this->logSuperAdminAction('cache.clear', 'Cleared application caches', []);

        Notification::make()
            ->title('Application caches cleared')
            ->body('Config, routes, views, and Filament caches have been refreshed.')
            ->success()
            ->send();
    }

    public function restartQueueWorkers(): void
    {
        if (! static::canAccess()) {
            abort(403);
        }

        Artisan::call('queue:restart');
        $this->lastMaintenanceActionAt = now()->toDateTimeString();
        $this->logSuperAdminAction('queue.restart', 'Restarted queue workers', []);

        Notification::make()
            ->title('Queue restart signal sent')
            ->body('Active workers will gracefully restart after the current job.')
            ->success()
            ->send();
    }

    public function previewPendingDriverApprovals(): void
    {
        if (! Schema::hasTable('drivers') || ! Schema::hasColumn('drivers', 'status')) {
            $this->driverApprovalPreview = 0;

            return;
        }

        $this->driverApprovalPreview = (int) DB::table('drivers')
            ->whereRaw('LOWER(CAST(status AS TEXT)) = ?', ['pending'])
            ->count();
    }

    public function approveAllPendingDrivers(): void
    {
        if (! static::canAccess()) {
            abort(403);
        }

        if (! Schema::hasTable('drivers') || ! Schema::hasColumn('drivers', 'status')) {
            return;
        }

        if (! trim($this->driverApprovalReason)) {
            Notification::make()
                ->title('Reason required')
                ->body('Please provide a reason for this bulk approval action.')
                ->warning()
                ->send();

            return;
        }

        $query = DB::table('drivers')->whereRaw('LOWER(CAST(status AS TEXT)) = ?', ['pending']);
        $updated = 0;
        $update = ['status' => 'approved'];

        if (Schema::hasColumn('drivers', 'approved_at')) {
            $update['approved_at'] = now();
        }

        if (Schema::hasColumn('drivers', 'updated_at')) {
            $update['updated_at'] = now();
        }

        $updated = $query->update($update);
        $this->refreshOperationsSnapshot();
        $this->logSuperAdminAction('drivers.bulk_approve', 'Bulk approved '.$updated.' pending drivers', [
            'count' => $updated,
            'reason' => $this->driverApprovalReason,
        ]);
        $this->driverApprovalReason = '';

        Notification::make()
            ->title('Pending drivers approved')
            ->body($updated.' driver account(s) moved from pending to approved.')
            ->success()
            ->send();
    }

    public function previewPendingUserApprovals(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_approved')) {
            $this->userApprovalPreview = 0;

            return;
        }

        $this->userApprovalPreview = (int) DB::table('users')
            ->where('is_approved', false)
            ->count();
    }

    public function approveAllPendingUsers(): void
    {
        if (! static::canAccess()) {
            abort(403);
        }

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'is_approved')) {
            return;
        }

        if (! trim($this->userApprovalReason)) {
            Notification::make()
                ->title('Reason required')
                ->body('Please provide a reason for this bulk approval action.')
                ->warning()
                ->send();

            return;
        }

        $userId = auth()->id();
        $query = DB::table('users')->where('is_approved', false);
        $update = [
            'is_approved' => true,
        ];

        if (Schema::hasColumn('users', 'updated_at')) {
            $update['updated_at'] = now();
        }

        if (Schema::hasColumn('users', 'approved_at')) {
            $update['approved_at'] = now();
        }

        if ($userId && Schema::hasColumn('users', 'approved_by')) {
            $update['approved_by'] = $userId;
        }

        $updated = $query->update($update);
        $this->refreshOperationsSnapshot();
        $this->logSuperAdminAction('users.bulk_approve', 'Bulk approved '.$updated.' pending users', [
            'count' => $updated,
            'reason' => $this->userApprovalReason,
        ]);
        $this->userApprovalReason = '';

        Notification::make()
            ->title('Pending users approved')
            ->body($updated.' user account(s) approved successfully.')
            ->success()
            ->send();
    }

    public function refreshOperationsSnapshot(): void
    {
        $pendingDrivers = 0;
        if (Schema::hasTable('drivers') && Schema::hasColumn('drivers', 'status')) {
            $pendingDrivers = (int) DB::table('drivers')
                ->whereRaw('LOWER(CAST(status AS TEXT)) = ?', ['pending'])
                ->count();
        }

        $pendingUsers = 0;
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'is_approved')) {
            $pendingUsers = (int) DB::table('users')
                ->where('is_approved', false)
                ->count();
        }

        $failedPayments24h = 0;
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'status') && Schema::hasColumn('payments', 'created_at')) {
            $failedPayments24h = (int) DB::table('payments')
                ->whereIn('status', ['failed', 'FAILED', 'declined', 'DECLINED'])
                ->where('created_at', '>=', now()->subHours(24))
                ->count();
        }

        $pendingOutbox = 0;
        if (Schema::hasTable('event_outbox') && Schema::hasColumn('event_outbox', 'status')) {
            $pendingOutbox = (int) DB::table('event_outbox')
                ->whereRaw('LOWER(CAST(status AS TEXT)) = ?', ['pending'])
                ->count();
        }

        $this->operationsSnapshot = [
            'pending_drivers' => $pendingDrivers,
            'pending_users' => $pendingUsers,
            'failed_payments_24h' => $failedPayments24h,
            'pending_outbox' => $pendingOutbox,
        ];
    }

    /**
     * Log a superadmin action for audit trail and compliance.
     */
    private function logSuperAdminAction(string $action, string $description, array $metadata = []): void
    {
        try {
            if (! Schema::hasTable('superadmin_action_logs')) {
                return;
            }

            $userId = auth()->id();
            $userEmail = auth()->user()?->email ?? 'unknown';

            DB::table('superadmin_action_logs')->insert([
                'user_id' => $userId,
                'user_email' => $userEmail,
                'action' => $action,
                'description' => $description,
                'metadata' => json_encode($metadata),
                'ip_address' => request()?->ip() ?? '127.0.0.1',
                'user_agent' => request()?->userAgent() ?? '',
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Silently fail; logging should not disrupt operations.
        }
    }

    public function downloadSuperAdminActionLogs(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! static::canAccess()) {
            abort(403);
        }

        if (! Schema::hasTable('superadmin_action_logs')) {
            Notification::make()
                ->title('No action logs available')
                ->body('Action logs table does not exist yet.')
                ->warning()
                ->send();

            return response()->stream(function () {}, 200, []);
        }

        $logs = DB::table('superadmin_action_logs')
            ->orderBy('created_at', 'desc')
            ->limit(10000)
            ->get();

        $filename = 'superadmin-action-logs-'.now()->format('Y-m-d-H-i-s').'.csv';

        return response()->streamDownload(function () use ($logs) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Timestamp', 'User Email', 'Action', 'Description', 'IP Address', 'Metadata']);

            foreach ($logs as $log) {
                fputcsv($output, [
                    $log->created_at,
                    $log->user_email,
                    $log->action,
                    $log->description,
                    $log->ip_address,
                    $log->metadata ?? '',
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function getColumns(): int|string|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 2,
        ];
    }
}
