<?php

namespace App\Filament\Widgets\Dashboard;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TransactionsTableWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.transactions-table-widget';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        [$table, $amountColumn] = $this->resolveFinanceSource();

        if (!$table || !$amountColumn) {
            return ['transactions' => collect()];
        }

        $query = DB::table($table)->latest('id')->limit(10);

        $columns = collect(['id', $amountColumn, 'status', 'created_at'])
            ->filter(fn (string $column) => Schema::hasColumn($table, $column))
            ->values()
            ->all();

        $transactions = $query->get($columns);

        return compact('transactions', 'amountColumn');
    }

    private function resolveFinanceSource(): array
    {
        foreach (['payments_v2', 'payments'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach (['amount', 'total_amount', 'fare_amount'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    return [$table, $column];
                }
            }
        }

        return [null, null];
    }
}
