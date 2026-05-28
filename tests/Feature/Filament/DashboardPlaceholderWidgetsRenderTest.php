<?php

namespace Tests\Feature\Filament;

use App\Filament\Widgets\Dashboard\AccountantPaymentHealthWidget;
use App\Filament\Widgets\Dashboard\AccountantPayoutPipelineWidget;
use App\Filament\Widgets\Dashboard\AccountantRevenueSummary;
use App\Filament\Widgets\Dashboard\CommissionOverviewWidget;
use App\Filament\Widgets\Dashboard\EscrowBalanceWidget;
use App\Filament\Widgets\Dashboard\FraudAlertsWidget;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPlaceholderWidgetsRenderTest extends TestCase
{
    public function test_placeholder_widgets_render_not_tracked_states(): void
    {
        Schema::shouldReceive('hasTable')->andReturnFalse();

        Livewire::test(FraudAlertsWidget::class)
            ->assertSee('Fraud telemetry not tracked yet')
            ->assertDontSee('Awaiting migrations');

        Livewire::test(EscrowBalanceWidget::class)
            ->assertSee('Ledger data not tracked yet')
            ->assertDontSee('Awaiting migrations')
            ->assertDontSee('RWF 0.00');

        Livewire::test(AccountantPaymentHealthWidget::class)
            ->assertSee('Payment telemetry not tracked yet')
            ->assertDontSee('0%')
            ->assertDontSee('RWF 0');

        Livewire::test(AccountantPayoutPipelineWidget::class)
            ->assertSee('Payout data not tracked yet')
            ->assertDontSee('Awaiting processing');

        Livewire::test(AccountantRevenueSummary::class)
            ->assertSee('Revenue data not tracked yet')
            ->assertDontSee('RWF 0.00');

        Livewire::test(CommissionOverviewWidget::class)
            ->assertSee('Commission data not tracked yet')
            ->assertDontSee('RWF 0.00');
    }
}