<div class="space-y-6">
    <section class="rounded-xl border-0 bg-gradient-to-r from-emerald-600 to-teal-600 p-6 text-white shadow-lg">
        <p class="text-xs font-semibold uppercase tracking-wider text-teal-100">RideConnect Finance Center</p>
        <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Accountant Dashboard</h1>
        <p class="mt-2 max-w-2xl text-sm text-teal-100 sm:text-base">
            Static Filament page with server-side financial metrics and transaction monitoring.
        </p>
    </section>

    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-dashboard-card title="Total Revenue" :value="'RWF ' . number_format($totalRevenue, 0)" subtitle="All-time processed" tone="emerald" />
        <x-dashboard-card title="This Month" :value="'RWF ' . number_format($monthlyRevenue, 0)" subtitle="Current month" tone="blue" />
        <x-dashboard-card title="Success (24h)" :value="number_format($successfulPayments24h)" subtitle="Payments completed" tone="purple" />
        <x-dashboard-card title="Pending Payouts" :value="number_format($pendingPayouts)" subtitle="Awaiting payout" tone="amber" />
    </section>

    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        <x-dashboard-card title="Pending Payout Amount" :value="'RWF ' . number_format($pendingPayoutAmount, 0)" subtitle="Cash flow waiting release" tone="amber" />
        <x-dashboard-card title="Commission Today" :value="'RWF ' . number_format($commissionToday, 0)" subtitle="Platform take today" tone="emerald" />
        <x-dashboard-card title="Retry Queue" :value="number_format($paymentRetryQueueCount)" subtitle="Failed payments to recover" tone="red" />
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">Finance Tools</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @if (auth()->user()->can('view finances'))
                <a href="{{ route('filament.admin.resources.payments.index') }}" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">Payments</a>
                <a href="{{ route('filament.admin.resources.commissions.index') }}" class="rounded-xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-700">Commissions</a>
                <a href="{{ route('filament.admin.resources.revenue.index') }}" class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-700">Revenue</a>
            @endif
            @if (\Illuminate\Support\Facades\Route::has('filament.admin.resources.driver-payouts.index') && auth()->user()->can('view finances'))
                <a href="{{ route('filament.admin.resources.driver-payouts.index') }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">Driver Payouts</a>
            @endif
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Recent Payments</h3>
            <p class="mt-1 text-xs text-slate-500">Failed in 24h: {{ number_format($failedPayments24h) }}</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="py-2 pr-3">ID</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2 pr-3">Amount</th>
                            <th class="py-2">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPayments as $payment)
                            @php
                                $amountValue = $payment['amount'] ?? $payment['total_amount'] ?? $payment['fare_amount'] ?? 0;
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-3 font-medium text-slate-900">#{{ $payment['id'] ?? '-' }}</td>
                                <td class="py-2 pr-3 text-slate-700">{{ strtoupper((string) ($payment['status'] ?? '-')) }}</td>
                                <td class="py-2 pr-3 text-slate-700">RWF {{ number_format((float) $amountValue, 0) }}</td>
                                <td class="py-2 text-slate-600">{{ $payment['created_at'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-slate-500">No payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Payment Retry Queue</h3>
            <p class="mt-1 text-xs text-slate-500">High priority failures to recover revenue and avoid driver disputes.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="py-2 pr-3">ID</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2 pr-3">Amount</th>
                            <th class="py-2">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($failedPayments as $payment)
                            @php
                                $amountValue = $payment['amount'] ?? $payment['total_amount'] ?? $payment['fare_amount'] ?? 0;
                            @endphp
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-3 font-medium text-slate-900">#{{ $payment['id'] ?? '-' }}</td>
                                <td class="py-2 pr-3 text-slate-700">{{ strtoupper((string) ($payment['status'] ?? '-')) }}</td>
                                <td class="py-2 pr-3 text-slate-700">RWF {{ number_format((float) $amountValue, 0) }}</td>
                                <td class="py-2 text-slate-600">{{ $payment['created_at'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-slate-500">No failed payments pending retry.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-base font-semibold text-slate-900">Pending Driver Payout Queue</h3>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-3">Payout</th>
                        <th class="py-2 pr-3">Driver</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2 pr-3">Amount</th>
                        <th class="py-2">Created</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingPayoutRows as $payout)
                        @php
                            $amountValue = $payout['amount'] ?? $payout['total_amount'] ?? $payout['net_amount'] ?? 0;
                        @endphp
                        <tr class="border-b border-slate-100">
                            <td class="py-2 pr-3 font-medium text-slate-900">#{{ $payout['id'] ?? '-' }}</td>
                            <td class="py-2 pr-3 text-slate-700">{{ $payout['driver_id'] ?? '-' }}</td>
                            <td class="py-2 pr-3 text-slate-700">{{ strtoupper((string) ($payout['status'] ?? '-')) }}</td>
                            <td class="py-2 pr-3 text-slate-700">RWF {{ number_format((float) $amountValue, 0) }}</td>
                            <td class="py-2 text-slate-600">{{ $payout['created_at'] ?? '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-slate-500">No pending payouts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
