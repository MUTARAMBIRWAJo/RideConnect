<?php

namespace App\Filament\Pages\Accountant;

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

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-banknotes';
    }

    public static function canAccess(): bool
    {
        return auth()->check() && (auth()->user()->hasRole('accountant') || auth()->user()->hasRole('ACCOUNTANT'));
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
        if (!Schema::hasTable('payments')) {
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
        $this->matchedCount = collect($this->transactions)->filter(fn ($t) => !($t['has_mismatch'] ?? false))->count();
        $this->mismatchedCount = $this->totalTransactions - $this->matchedCount;
        $this->totalMismatchAmount = collect($this->transactions)
            ->filter(fn ($t) => $t['has_mismatch'] ?? false)
            ->sum(fn ($t) => abs(($t['estimated_fare'] ?? 0) - ($t['actual_fare'] ?? 0)))
            ?? 0.0;
    }

    public function reviewTransaction(int $transactionId): void
    {
        if (!auth()->user()->can('view finances')) {
            abort(403);
        }

        DB::table('payments')
            ->where('id', $transactionId)
            ->update(['reviewed' => true, 'reviewed_at' => now()]);

        $this->loadTransactions();
    }
}
