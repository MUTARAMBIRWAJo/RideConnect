<?php

namespace App\Filament\Pages\Accountant;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RefundManagementPage extends Page
{
    protected static ?string $navigationGroup = 'Financial Operations';

    protected static string $view = 'filament.pages.accountant.refund-management';

    /** @var array<int, array<string, mixed>> */
    public array $refundRequests = [];

    public int $pendingRefunds = 0;

    public int $approvedRefunds = 0;

    public float $totalRefundAmount = 0.0;

    public ?int $adjustRideId = null;

    public ?float $adjustFareAmount = null;

    public string $adjustReason = 'customer_complaint';

    public static function getNavigationLabel(): string
    {
        return 'Refund Management';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-arrow-uturn-left';
    }

    public static function canAccess(): bool
    {
        return auth()->check() && (auth()->user()->hasRole('accountant') || auth()->user()->hasRole('ACCOUNTANT'));
    }

    public function getTitle(): string
    {
        return 'Refund & Adjustment Management';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadRefundRequests();
    }

    private function loadRefundRequests(): void
    {
        // Try to load refund requests from database
        if (Schema::hasTable('refunds')) {
            $columns = collect(['id', 'ride_id', 'amount', 'reason', 'status', 'created_at', 'user_id'])
                ->filter(fn (string $column): bool => Schema::hasColumn('refunds', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $this->refundRequests = DB::table('refunds')
                    ->select($columns)
                    ->latest('id')
                    ->limit(50)
                    ->get()
                    ->map(fn ($row): array => (array) $row)
                    ->all();

                $this->pendingRefunds = collect($this->refundRequests)->filter(fn ($r) => in_array($r['status'] ?? '', ['pending', 'PENDING'], true))->count();
                $this->approvedRefunds = collect($this->refundRequests)->filter(fn ($r) => in_array($r['status'] ?? '', ['approved', 'APPROVED', 'completed', 'COMPLETED'], true))->count();
                $this->totalRefundAmount = (float) array_sum(array_column($this->refundRequests, 'amount'));
                return;
            }
        }

        // Fallback: create mock data for demonstration
        $this->refundRequests = [
            ['id' => 1, 'ride_id' => 1001, 'amount' => 15.50, 'reason' => 'Incorrect fare calculation', 'status' => 'pending', 'created_at' => now()->subHours(3)->toDateTimeString()],
            ['id' => 2, 'ride_id' => 1002, 'amount' => 8.00, 'reason' => 'Driver canceled after pickup', 'status' => 'approved', 'created_at' => now()->subHours(12)->toDateTimeString()],
            ['id' => 3, 'ride_id' => 1003, 'amount' => 22.75, 'reason' => 'Service issue - driver went wrong route', 'status' => 'pending', 'created_at' => now()->subHours(5)->toDateTimeString()],
        ];

        $this->pendingRefunds = 2;
        $this->approvedRefunds = 1;
        $this->totalRefundAmount = 46.25;
    }

    public function approveRefund(int $refundId): void
    {
        if (!auth()->user()->can('manage finances')) {
            abort(403);
        }

        if (!Schema::hasTable('refunds')) {
            return;
        }

        $update = [];
        if (Schema::hasColumn('refunds', 'status')) {
            $update['status'] = 'approved';
        }
        if (Schema::hasColumn('refunds', 'approved_at')) {
            $update['approved_at'] = now();
        }
        if (Schema::hasColumn('refunds', 'approved_by')) {
            $update['approved_by'] = auth()->id();
        }
        if (Schema::hasColumn('refunds', 'updated_at')) {
            $update['updated_at'] = now();
        }

        DB::table('refunds')->where('id', $refundId)->update($update);

        $this->loadRefundRequests();

        Notification::make()
            ->title('Refund approved successfully')
            ->success()
            ->send();
    }

    public function rejectRefund(int $refundId, string $reason = 'rejected_by_accountant'): void
    {
        if (!auth()->user()->can('manage finances')) {
            abort(403);
        }

        if (!Schema::hasTable('refunds')) {
            return;
        }

        $update = [];
        if (Schema::hasColumn('refunds', 'status')) {
            $update['status'] = 'rejected';
        }
        if (Schema::hasColumn('refunds', 'rejection_reason')) {
            $update['rejection_reason'] = $reason;
        }
        if (Schema::hasColumn('refunds', 'rejected_at')) {
            $update['rejected_at'] = now();
        }
        if (Schema::hasColumn('refunds', 'updated_at')) {
            $update['updated_at'] = now();
        }

        DB::table('refunds')->where('id', $refundId)->update($update);

        $this->loadRefundRequests();

        Notification::make()
            ->title('Refund rejected')
            ->warning()
            ->send();
    }

    public function adjustFare(): void
    {
        if (!auth()->user()->can('manage finances')) {
            abort(403);
        }

        if (!Schema::hasTable('payments') || $this->adjustRideId === null || $this->adjustFareAmount === null) {
            Notification::make()
                ->title('Ride ID and fare amount are required')
                ->danger()
                ->send();

            return;
        }

        $update = [];
        if (Schema::hasColumn('payments', 'actual_fare')) {
            $update['actual_fare'] = $this->adjustFareAmount;
        }
        if (Schema::hasColumn('payments', 'adjustment_reason')) {
            $update['adjustment_reason'] = $this->adjustReason;
        }
        if (Schema::hasColumn('payments', 'adjusted_at')) {
            $update['adjusted_at'] = now();
        }
        if (Schema::hasColumn('payments', 'updated_at')) {
            $update['updated_at'] = now();
        }

        DB::table('payments')->where('ride_id', $this->adjustRideId)->update($update);

        $this->adjustRideId = null;
        $this->adjustFareAmount = null;
        $this->adjustReason = 'customer_complaint';

        $this->loadRefundRequests();

        Notification::make()
            ->title('Fare adjustment applied successfully')
            ->success()
            ->send();
    }
}
