<?php

namespace App\Filament\Pages\Accountant;

use App\Enums\UserRole;
use App\Services\ActionAuditLogger;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransactionsPage extends Page
{
    protected static ?string $navigationGroup = 'Financial Operations';

    protected static string $view = 'filament.pages.accountant.transactions';

    /** @var array<int, array<string, mixed>> */
    public array $transactions = [];

    public int $totalTransactions = 0;

    public int $matchedCount = 0;

    public int $mismatchedCount = 0;

    public float $totalMismatchAmount = 0.0;

    public static function getNavigationLabel(): string
    {
        return 'Transactions';
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-banknotes';
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
        return 'Ride Transactions & Fare Review';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadTransactions();
    }

    private function loadTransactions(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        $columns = collect(['id', 'ride_id', 'amount', 'estimated_fare', 'actual_fare', 'status', 'created_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('payments', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        $this->transactions = DB::table('payments')
            ->select($columns)
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function ($row): array {
                $row = (array) $row;
                $row['has_mismatch'] = abs(($row['estimated_fare'] ?? 0) - ($row['actual_fare'] ?? 0)) > 0.01;

                return $row;
            })
            ->all();

        $this->totalTransactions = count($this->transactions);
        $this->matchedCount = collect($this->transactions)->filter(fn ($t) => ! ($t['has_mismatch'] ?? false))->count();
        $this->mismatchedCount = $this->totalTransactions - $this->matchedCount;
        $this->totalMismatchAmount = collect($this->transactions)
            ->filter(fn ($t) => $t['has_mismatch'] ?? false)
            ->sum(fn ($t) => abs(($t['estimated_fare'] ?? 0) - ($t['actual_fare'] ?? 0)))
            ?? 0.0;
    }

    public function reviewTransaction(int $transactionId): void
    {
        if (! auth()->user()->can('view finances')) {
            abort(403);
        }

        $updates = [];
        if (Schema::hasColumn('payments', 'reviewed')) {
            $updates['reviewed'] = true;
        }
        if (Schema::hasColumn('payments', 'reviewed_at')) {
            $updates['reviewed_at'] = now();
        }
        if (Schema::hasColumn('payments', 'metadata')) {
            $currentMetadata = DB::table('payments')->where('id', $transactionId)->value('metadata');

            if (is_string($currentMetadata)) {
                $decoded = json_decode($currentMetadata, true);
                $currentMetadata = is_array($decoded) ? $decoded : [];
            }

            if (! is_array($currentMetadata)) {
                $currentMetadata = [];
            }

            $currentMetadata['reviewed_by_user_id'] = (int) auth()->id();
            $currentMetadata['reviewed_at'] = now()->toIso8601String();
            $updates['metadata'] = $currentMetadata;
        }
        if (Schema::hasColumn('payments', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        if ($updates !== []) {
            DB::table('payments')
                ->where('id', $transactionId)
                ->update($updates);
        }

        app(ActionAuditLogger::class)->log(
            'transaction.review',
            'Accountant reviewed transaction #'.$transactionId,
            ['payment_id' => $transactionId],
        );

        $this->loadTransactions();

        Notification::make()
            ->title('Transaction reviewed successfully')
            ->success()
            ->send();
    }
}
