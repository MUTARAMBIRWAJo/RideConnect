<x-filament-panels::page>
    <div class="space-y-6" wire:poll.60s>
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-green-600 to-emerald-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-green-100">Income & Earnings</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Driver Earnings</h1>
            <p class="mt-2 max-w-2xl text-sm text-green-100 sm:text-base">
                Track driver earnings history, commission deductions, and settlement information.
            </p>
        </section>

        <!-- Summary Stats -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-dashboard-card title="Total Paid" :value="'$' . number_format($totalPaidOut, 2)" subtitle="To all drivers" tone="green" />
            <x-dashboard-card title="Commissions" :value="'$' . number_format($totalCommissionEarned, 2)" subtitle="Platform earned" tone="amber" />
            <x-dashboard-card title="Drivers Listed" :value="number_format($totalDriverCount)" subtitle="In earnings system" tone="blue" />
        </section>

        <!-- Driver Earnings Table -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Driver Earnings Breakdown</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 font-semibold">
                            <th class="py-3 pr-3">Driver ID</th>
                            <th class="py-3 pr-3">Gross Amount</th>
                            <th class="py-3 pr-3">Commission</th>
                            <th class="py-3 pr-3">Net Earnings</th>
                            <th class="py-3 pr-3">Status</th>
                            <th class="py-3">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($driverEarnings as $earning)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                                <td class="py-3 pr-3 font-mono text-slate-700">#{{ $earning['driver_id'] ?? '-' }}</td>
                                <td class="py-3 pr-3 font-semibold text-slate-900">${{ number_format($earning['amount'] ?? 0, 2) }}</td>
                                <td class="py-3 pr-3 text-red-600">-${{ number_format($earning['commission_deducted'] ?? 0, 2) }}</td>
                                <td class="py-3 pr-3 font-semibold text-green-600">${{ number_format($earning['net_earnings'] ?? 0, 2) }}</td>
                                <td class="py-3 pr-3">
                                    <span class="inline-block rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700">
                                        {{ strtoupper($earning['status'] ?? 'PENDING') }}
                                    </span>
                                </td>
                                <td class="py-3">
                                    <a href="{{ \App\Filament\Pages\Accountant\ReportsPage::getUrl(panel: 'accountant') }}?report=settlement&driver={{ $earning['driver_id'] ?? '' }}" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200 transition">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-500 text-sm">No driver earnings data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Info Box -->
        <section class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <p class="text-xs text-blue-800">
                ℹ️ <strong>Earnings Calculation:</strong> Gross amount represents total ride revenue earned by the driver.
                Commission is deducted by the platform. Net earnings = Gross - Commission.
            </p>
        </section>
    </div>
</x-filament-panels::page>
