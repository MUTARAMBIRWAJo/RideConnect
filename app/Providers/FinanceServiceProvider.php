<?php

namespace App\Providers;

use App\Contracts\EventBusInterface;
use App\Modules\Compliance\Contracts\ComplianceReportRepositoryInterface;
use App\Modules\Compliance\Repositories\ComplianceReportRepository;
use App\Modules\Finance\Contracts\LedgerRepositoryInterface;
use App\Modules\Finance\Contracts\PaymentRepositoryInterface;
use App\Modules\Finance\Repositories\LedgerRepository;
use App\Modules\Finance\Repositories\PaymentRepository;
use App\Modules\Finance\Services\FinanceService;
use App\Modules\Reporting\Contracts\ReportingRepositoryInterface;
use App\Modules\Reporting\Repositories\ReportingRepository;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\Settlement\Contracts\SettlementRepositoryInterface;
use App\Modules\Settlement\Repositories\SettlementRepository;
use App\Modules\Settlement\Services\SettlementService;
use App\Modules\Tax\Contracts\TaxRuleRepositoryInterface;
use App\Modules\Tax\Repositories\TaxRuleRepository;
use App\Modules\Tax\Services\TaxService;
use App\Services\EventSourcing\Drivers\DatabaseEventBus;
use App\Services\EventSourcing\Drivers\KafkaEventBus;
use App\Services\EventSourcing\Drivers\PulsarEventBus;
use App\Services\EventSourcing\EventDispatcherService;
use App\Services\EventSourcing\EventPublisherService;
use App\Services\EventSourcing\OutboxService;
use Illuminate\Support\ServiceProvider;

/**
 * FinanceServiceProvider
 *
 * Registers all Finance domain module bindings.
 * Each interface → concrete implementation mapping is central here.
 *
 * To swap Kafka for database bus: set EVENT_BUS_DRIVER=kafka in .env
 * To swap all repositories for microservice HTTP clients: replace bindings here only.
 */
class FinanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // -----------------------------------------------------------------------
        // Event Bus — swap driver via EVENT_BUS_DRIVER env
        // -----------------------------------------------------------------------
        $this->app->singleton(EventBusInterface::class, function ($app) {
            return match (config('event_bus.driver', 'database')) {
                'kafka'  => new KafkaEventBus(),
                'pulsar' => new PulsarEventBus(),
                default  => new DatabaseEventBus(),
            };
        });

        // -----------------------------------------------------------------------
        // Event Sourcing Infrastructure
        // -----------------------------------------------------------------------
        $this->app->singleton(OutboxService::class);

        $this->app->singleton(EventDispatcherService::class, function ($app) {
            return new EventDispatcherService(
                $app->make(EventBusInterface::class),
                $app->make(OutboxService::class),
            );
        });

        $this->app->singleton(EventPublisherService::class, function ($app) {
            return new EventPublisherService(
                $app->make(EventBusInterface::class),
                $app->make(OutboxService::class),
            );
        });

        // -----------------------------------------------------------------------
        // Finance Module
        // -----------------------------------------------------------------------
        $this->app->bind(LedgerRepositoryInterface::class, LedgerRepository::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);

        $this->app->singleton(FinanceService::class, function ($app) {
            return new FinanceService(
                $app->make(\App\Services\LedgerService::class),
                $app->make(\App\Services\WalletService::class),
                $app->make(LedgerRepositoryInterface::class),
                $app->make(PaymentRepositoryInterface::class),
                $app->make(EventDispatcherService::class),
            );
        });

        // -----------------------------------------------------------------------
        // Tax Module
        // -----------------------------------------------------------------------
        $this->app->bind(TaxRuleRepositoryInterface::class, TaxRuleRepository::class);

        $this->app->singleton(TaxService::class, function ($app) {
            return new TaxService(
                $app->make(TaxRuleRepositoryInterface::class),
                $app->make(\App\Services\LedgerService::class),
            );
        });

        // -----------------------------------------------------------------------
        // Settlement Module
        // -----------------------------------------------------------------------
        $this->app->bind(SettlementRepositoryInterface::class, SettlementRepository::class);

        $this->app->singleton(SettlementService::class, function ($app) {
            return new SettlementService(
                $app->make(SettlementRepositoryInterface::class),
                $app->make(\App\Services\LedgerService::class),
                $app->make(\App\Services\WalletService::class),
                $app->make(TaxService::class),
                $app->make(EventDispatcherService::class),
            );
        });

        // -----------------------------------------------------------------------
        // Compliance Module
        // -----------------------------------------------------------------------
        $this->app->bind(ComplianceReportRepositoryInterface::class, ComplianceReportRepository::class);

        // -----------------------------------------------------------------------
        // Reporting Module
        // -----------------------------------------------------------------------
        $this->app->bind(ReportingRepositoryInterface::class, ReportingRepository::class);

        $this->app->singleton(ReportingService::class, function ($app) {
            return new ReportingService(
                $app->make(ReportingRepositoryInterface::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
