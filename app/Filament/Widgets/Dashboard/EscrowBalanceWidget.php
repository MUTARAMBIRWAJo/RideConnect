<?php

namespace App\Filament\Widgets\Dashboard;

use App\Services\LedgerService;
use App\Services\WalletService;
use App\Models\DriverPayout;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class EscrowBalanceWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        if (! Schema::hasTable('ledger_accounts') || ! Schema::hasTable('ledger_entries')) {
            return $this->emptyStats();
        }

        try {
            /** @var LedgerService $ledger */
            $ledger = app(LedgerService::class);

            $escrow         = $ledger->getEscrowBalance();
            $driverWallets  = $ledger->getTotalDriverWalletBalance();
            $platformRevenue = $ledger->getPlatformRevenue();

            $pendingSettlements = Schema::hasTable('driver_payouts')
                ? (int) DriverPayout::query()->where('status', 'pending')->count()
                : 0;

        } catch (\Throwable) {
            return $this->emptyStats();
        }

        return [
            Stat::make('Total Escrow Balance', 'RWF ' . number_format($escrow, 2))
                ->description('Funds held pending settlement')
                ->color('warning')
                ->icon('heroicon-o-lock-closed'),

            Stat::make('Total Driver Wallet Balance', 'RWF ' . number_format($driverWallets, 2))
                ->description('Net settled driver earnings')
                ->color('info')
                ->icon('heroicon-o-wallet'),

            Stat::make('Total Platform Revenue', 'RWF ' . number_format($platformRevenue, 2))
                ->description('Cumulative 8% commission retained')
                ->color('success')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Pending Settlements', (string) $pendingSettlements)
                ->description('Payouts awaiting processing')
                ->color($pendingSettlements > 0 ? 'danger' : 'gray')
                ->icon('heroicon-o-clock'),
        ];
    }

    private function emptyStats(): array
    {
        return [
            Stat::make('Total Escrow Balance', 'RWF 0.00')->description('Awaiting migrations')->color('gray'),
            Stat::make('Total Driver Wallet Balance', 'RWF 0.00')->description('Awaiting migrations')->color('gray'),
            Stat::make('Total Platform Revenue', 'RWF 0.00')->description('Awaiting migrations')->color('gray'),
            Stat::make('Pending Settlements', '0')->description('Awaiting migrations')->color('gray'),
        ];
    }
}
