<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-amber-600 to-orange-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-amber-100">Financial Dashboard</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Financial Overview</h1>
            <p class="mt-2 max-w-2xl text-sm text-amber-100 sm:text-base">
                Real-time financial metrics, revenue tracking, and payment management.
            </p>
        </section>

        <!-- Primary KPI Cards -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-dashboard-card title="Total Revenue" :value="'$' . number_format($totalRevenue, 2)" subtitle="All time" tone="amber" />
            <x-dashboard-card title="Monthly Revenue" :value="'$' . number_format($monthlyRevenue, 2)" subtitle="This month" tone="orange" />
            <x-dashboard-card title="Commissions Today" :value="'$' . number_format($commissionToday, 2)" subtitle="Earned today" tone="green" />
            <x-dashboard-card title="Pending Payouts" :value="'$' . number_format($pendingPayoutAmount, 2)" subtitle="{{ $pendingPayouts }} drivers" tone="red" />
        </section>

        <!-- Payment Stats -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-dashboard-card title="Successful (24h)" :value="number_format($successfulPayments24h)" subtitle="Completed transactions" tone="green" />
            <x-dashboard-card title="Failed (24h)" :value="number_format($failedPayments24h)" subtitle="Needs review" tone="red" />
        </section>

        <!-- Quick Actions & Tools -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Financial Tools</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <a href="{{ \App\Filament\Pages\Accountant\TransactionsPage::getUrl(panel: 'accountant') }}" class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-center text-sm font-medium text-blue-700 hover:bg-blue-100 transition">
                    💰 Transactions
                </a>
                <a href="{{ \App\Filament\Pages\Accountant\DriverEarningsPage::getUrl(panel: 'accountant') }}" class="rounded-xl border border-green-200 bg-green-50 p-3 text-center text-sm font-medium text-green-700 hover:bg-green-100 transition">
                    📊 Earnings
                </a>
                <a href="{{ \App\Filament\Pages\Accountant\ReportsPage::getUrl(panel: 'accountant') }}" class="rounded-xl border border-purple-200 bg-purple-50 p-3 text-center text-sm font-medium text-purple-700 hover:bg-purple-100 transition">
                    📋 Reports
                </a>
                <a href="{{ \App\Filament\Pages\Accountant\RefundManagementPage::getUrl(panel: 'accountant') }}" class="rounded-xl border border-orange-200 bg-orange-50 p-3 text-center text-sm font-medium text-orange-700 hover:bg-orange-100 transition">
                    ↩️ Refunds
                </a>
                <a href="{{ \App\Filament\Pages\Accountant\AuditLogsPage::getUrl(panel: 'accountant') }}" class="rounded-xl border border-red-200 bg-red-50 p-3 text-center text-sm font-medium text-red-700 hover:bg-red-100 transition">
                    🔍 Audit
                </a>
            </div>
        </section>

        <!-- Recent Payments & Failed Payments -->
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-900">Recent Payments</h3>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="py-2 pr-3">ID</th>
                                <th class="py-2 pr-3">Amount</th>
                                <th class="py-2 pr-3">Status</th>
                                <th class="py-2">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentPayments as $payment)
                                <tr class="border-b border-slate-100">
                                    <td class="py-2 pr-3 font-mono text-slate-700">#{{ $payment['id'] ?? '-' }}</td>
                                    <td class="py-2 pr-3 font-semibold text-slate-900">${{ number_format($payment['amount'] ?? 0, 2) }}</td>
                                    <td class="py-2 pr-3">
                                        <span class="inline-block rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                            {{ strtoupper($payment['status'] ?? '-') }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-slate-600 text-xs">{{ \Carbon\Carbon::parse($payment['created_at'])->format('M d, H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-slate-500">No payments recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
                <h3 class="text-base font-semibold text-red-900">Failed Payments (24h)</h3>
                <p class="mt-1 text-xs text-red-700">Transactions needing retry or manual review.</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-red-200 text-left text-xs uppercase tracking-wide text-red-700">
                                <th class="py-2 pr-3">ID</th>
                                <th class="py-2 pr-3">Amount</th>
                                <th class="py-2 pr-3">Retries</th>
                                <th class="py-2">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($failedPayments as $payment)
                                <tr class="border-b border-red-100">
                                    <td class="py-2 pr-3 font-mono text-red-700">#{{ $payment['id'] ?? '-' }}</td>
                                    <td class="py-2 pr-3 font-semibold text-red-900">${{ number_format($payment['amount'] ?? 0, 2) }}</td>
                                    <td class="py-2 pr-3 text-red-700">{{ $payment['retry_count'] ?? 0 }}/3</td>
                                    <td class="py-2">
                                        <x-filament::modal width="md">
                                            <x-slot name="trigger">
                                                <button type="button" class="text-xs bg-red-600 text-white px-2 py-1 rounded hover:bg-red-700 transition">Retry</button>
                                            </x-slot>

                                            <div class="space-y-4">
                                                <p class="text-sm text-slate-700">Retry this failed payment now?</p>
                                                <div class="flex justify-end">
                                                    <x-filament::button size="sm" color="danger" wire:click="retryPayment({{ (int) ($payment['id'] ?? 0) }})">Confirm Retry</x-filament::button>
                                                </div>
                                            </div>
                                        </x-filament::modal>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-red-600">✓ All payments processed</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Pending Payouts Queue -->
        <section class="rounded-xl border border-orange-200 bg-orange-50 p-5 shadow-sm">
            <h3 class="text-base font-semibold text-orange-900">⏳ Pending Driver Payout Queue</h3>
            <p class="mt-1 text-xs text-orange-700">Driver settlements awaiting approval.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-orange-200 text-left text-xs uppercase tracking-wide text-orange-700">
                            <th class="py-2 pr-3">ID</th>
                            <th class="py-2 pr-3">Driver</th>
                            <th class="py-2 pr-3">Amount</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingPayoutRows as $payout)
                            <tr class="border-b border-orange-100">
                                <td class="py-2 pr-3 font-mono text-orange-700">#{{ $payout['id'] ?? '-' }}</td>
                                <td class="py-2 pr-3 text-orange-900">Driver #{{ $payout['driver_id'] ?? '-' }}</td>
                                <td class="py-2 pr-3 font-semibold text-orange-900">${{ number_format($payout['amount'] ?? 0, 2) }}</td>
                                <td class="py-2 pr-3">
                                    <span class="inline-block rounded-full bg-orange-200 px-2 py-1 text-xs font-medium text-orange-900">
                                        {{ strtoupper($payout['status'] ?? 'PENDING') }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    <x-filament::modal width="md">
                                        <x-slot name="trigger">
                                            <button type="button" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200 transition">Approve</button>
                                        </x-slot>

                                        <div class="space-y-4">
                                            <p class="text-sm text-slate-700">Approve this pending payout?</p>
                                            <div class="flex justify-end">
                                                <x-filament::button size="sm" color="success" wire:click="approvePayout({{ (int) ($payout['id'] ?? 0) }})">Confirm Approve</x-filament::button>
                                            </div>
                                        </div>
                                    </x-filament::modal>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-orange-600">✓ No pending payouts</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
