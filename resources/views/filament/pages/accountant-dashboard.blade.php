<x-filament-panels::page>
    <div class="space-y-6" wire:poll.60s>
        <x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-amber-500 via-orange-500 to-yellow-500 text-white shadow-xl">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-100 flex items-center gap-2">
                        <x-heroicon-o-banknotes class="w-4 h-4" />
                        RideConnect Finance Center
                    </p>
                    <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Accountant Dashboard</h1>
                    <p class="mt-2 max-w-2xl text-sm text-amber-100 sm:text-base">
                        Track revenue, payments, and financial metrics with comprehensive reporting and analytics.
                    </p>
                </div>

                <div class="inline-flex items-center rounded-lg bg-white/20 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/30 backdrop-blur-sm">
                    <x-heroicon-o-clock class="w-4 h-4 mr-2" />
                    Real-time financial data updates
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Financial Operations</x-slot>
            <x-slot name="description">Key financial management tools, exports, and payout controls.</x-slot>

            <div class="grid gap-4 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-4">
                @if (auth()->user()->can('view finances'))
                    <a href="{{ route('filament.accountant.pages.transactions-page') }}" class="group rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-yellow-50 p-4 sm:p-5 text-sm text-amber-800 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-amber-800 dark:bg-gradient-to-br dark:from-amber-900 dark:to-yellow-900 dark:text-amber-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-800">
                                <x-heroicon-o-credit-card class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                            </div>
                            <p class="font-semibold">Payments</p>
                        </div>
                        <p class="text-amber-700 dark:text-amber-300">Review and manage all payment records.</p>
                    </a>
                @endif

                @if (auth()->user()->can('view finances'))
                    <a href="{{ route('filament.accountant.pages.transactions-page') }}" class="group rounded-xl border border-cyan-200 bg-gradient-to-br from-cyan-50 to-blue-50 p-4 sm:p-5 text-sm text-cyan-800 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-cyan-800 dark:bg-gradient-to-br dark:from-cyan-900 dark:to-blue-900 dark:text-cyan-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-cyan-100 dark:bg-cyan-800">
                                <x-heroicon-o-calculator class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                            </div>
                            <p class="font-semibold">Commissions</p>
                        </div>
                        <p class="text-cyan-700 dark:text-cyan-300">Track driver and partner commissions.</p>
                    </a>
                @endif

                @if (auth()->user()->can('view finances'))
                    <a href="{{ route('filament.accountant.pages.reports-page') }}" class="group rounded-xl border border-teal-200 bg-gradient-to-br from-teal-50 to-green-50 p-4 sm:p-5 text-sm text-teal-800 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-teal-800 dark:bg-gradient-to-br dark:from-teal-900 dark:to-green-900 dark:text-teal-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-teal-100 dark:bg-teal-800">
                                <x-heroicon-o-chart-bar class="w-5 h-5 text-teal-600 dark:text-teal-400" />
                            </div>
                            <p class="font-semibold">Revenue</p>
                        </div>
                        <p class="text-teal-700 dark:text-teal-300">Monitor revenue trends and summaries.</p>
                    </a>
                @endif

                @if (auth()->user()->can('view finances'))
                    <a href="{{ route('filament.accountant.pages.driver-earnings-page') }}" class="group rounded-xl border border-orange-200 bg-gradient-to-br from-orange-50 to-red-50 p-4 sm:p-5 text-sm text-orange-800 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-orange-800 dark:bg-gradient-to-br dark:from-orange-900 dark:to-red-900 dark:text-orange-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-orange-100 dark:bg-orange-800">
                                <x-heroicon-o-currency-dollar class="w-5 h-5 text-orange-600 dark:text-orange-400" />
                            </div>
                            <p class="font-semibold">Driver Payouts</p>
                        </div>
                        <p class="text-orange-700 dark:text-orange-300">Track pending and processed payout pipeline.</p>
                    </a>
                @endif
            </div>
        </x-filament::section>

        <x-filament-widgets::widgets
            :columns="$this->getColumns()"
            :widgets="$this->getWidgets()"
            :data="$this->getWidgetData()"
        />
    </div>
</x-filament-panels::page>
