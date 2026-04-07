<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-100">Financial Analysis</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Transactions Review</h1>
            <p class="mt-2 max-w-2xl text-sm text-blue-100 sm:text-base">
                Analyze ride transactions, identify fare mismatches, and ensure pricing accuracy.
            </p>
        </section>

        <!-- Stats Row -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-dashboard-card title="Total Transactions" :value="number_format($totalTransactions)" subtitle="Under review" tone="blue" />
            <x-dashboard-card title="Matched Fares" :value="number_format($matchedCount)" subtitle="No discrepancy" tone="green" />
            <x-dashboard-card title="Mismatched" :value="number_format($mismatchedCount)" subtitle="Needs review" tone="red" />
        </section>

        <!-- Mismatch Summary -->
        @if ($mismatchedCount > 0)
            <section class="rounded-xl border-2 border-red-200 bg-red-50 p-5 shadow-sm">
                <h2 class="text-base font-semibold text-red-900">⚠️ Fare Mismatch Summary</h2>
                <p class="mt-1 text-sm text-red-800">
                    Found <strong>{{ $mismatchedCount }}</strong> transactions with discrepancies between estimated and actual fares.
                    Total mismatch amount: <strong>${{ number_format($totalMismatchAmount, 2) }}</strong>
                </p>
                <div class="mt-3 flex gap-2">
                    <button class="px-4 py-2 bg-red-600 text-white text-sm rounded hover:bg-red-700 transition">Investigate All</button>
                    <button class="px-4 py-2 bg-red-100 text-red-700 text-sm rounded hover:bg-red-200 transition">Export Report</button>
                </div>
            </section>
        @endif

        <!-- Transactions Table -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">All Transactions</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 font-semibold">
                            <th class="py-3 pr-3">Ride ID</th>
                            <th class="py-3 pr-3">Amount</th>
                            <th class="py-3 pr-3">Estimated</th>
                            <th class="py-3 pr-3">Actual</th>
                            <th class="py-3 pr-3">Match</th>
                            <th class="py-3 pr-3">Status</th>
                            <th class="py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transactions as $transaction)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition {{ ($transaction['has_mismatch'] ?? false) ? 'bg-red-50' : '' }}">
                                <td class="py-3 pr-3 font-mono text-slate-700">#{{ $transaction['id'] ?? '-' }}</td>
                                <td class="py-3 pr-3 font-semibold text-slate-900">${{ number_format($transaction['amount'] ?? 0, 2) }}</td>
                                <td class="py-3 pr-3 text-slate-700">${{ number_format($transaction['estimated_fare'] ?? 0, 2) }}</td>
                                <td class="py-3 pr-3 text-slate-700">${{ number_format($transaction['actual_fare'] ?? 0, 2) }}</td>
                                <td class="py-3 pr-3">
                                    @if ($transaction['has_mismatch'] ?? false)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">
                                            ⚠️ Mismatch
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                            ✓ OK
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 pr-3">
                                    <span class="inline-block rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700">
                                        {{ strtoupper($transaction['status'] ?? '-') }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <button class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200 transition">Review</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-500 text-sm">No transactions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Legend -->
        <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-medium text-slate-700">
                ℹ️ <strong>Fare Matching:</strong> Compares estimated fare (calculated at booking) with actual fare (charged after completion).
                Discrepancies may indicate dynamic pricing, route changes, or surge pricing.
            </p>
        </section>
    </div>
</x-filament-panels::page>
