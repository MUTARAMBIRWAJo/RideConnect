<x-filament-panels::page>
    <div class="space-y-6" wire:poll.60s>
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-indigo-600 to-violet-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-100">Audit & Compliance</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Audit Logs</h1>
            <p class="mt-2 max-w-2xl text-sm text-indigo-100 sm:text-base">
                Complete audit trail of all financial transactions, modifications, and compliance updates.
            </p>
        </section>

        <!-- Audit Stats -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-dashboard-card title="Total Audit Entries" :value="number_format($totalAuditEntries)" subtitle="All records" tone="indigo" />
            <x-dashboard-card title="Suspicious Transactions" :value="number_format($suspiciousTransactions)" subtitle="Flagged for review" tone="red" />
        </section>

        <!-- Audit Log Table -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Financial Audit Trail</h2>
            <p class="mt-1 text-xs text-slate-600">Complete history of all financial operations and modifications.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 font-semibold">
                            <th class="py-3 pr-3">Entry ID</th>
                            <th class="py-3 pr-3">Ride/Transaction</th>
                            <th class="py-3 pr-3">Fare Difference</th>
                            <th class="py-3 pr-3">Status</th>
                            <th class="py-3 pr-3">Actor</th>
                            <th class="py-3">Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($auditLogs as $log)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                                <td class="py-3 pr-3 font-mono text-slate-700">#{{ $log['id'] ?? '-' }}</td>
                                <td class="py-3 pr-3 font-medium text-slate-900">
                                    {{ $log['ride_id'] ?? $log['subject_id'] ?? '—' }}
                                </td>
                                <td class="py-3 pr-3">
                                    <span class="font-mono font-semibold {{ ($log['fare_difference'] ?? 0) > 0 ? 'text-orange-600' : 'text-slate-700' }}">
                                        {{ ($log['fare_difference'] ?? 0) > 0 ? '+' : '' }}RWF {{ number_format($log['fare_difference'] ?? 0, 2) }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3">
                                    <span class="inline-block rounded-full {{ ($log['status'] ?? '') === 'Suspicious' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }} px-2 py-1 text-xs font-medium">
                                        {{ $log['status'] ?? 'VALID' }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3 text-slate-600">{{ $log['actor'] ?? 'System' }}</td>
                                <td class="py-3 text-xs text-slate-500">{{ \Carbon\Carbon::parse($log['created_at'])->format('M d, H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-slate-500 text-sm">No audit entries found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Compliance Info -->
        <section class="rounded-xl border border-purple-200 bg-purple-50 p-4">
            <p class="text-xs text-purple-900">
                🔒 <strong>Audit Compliance:</strong> This log contains immutable records of all financial transactions and
                modifications. All entries are timestamped and attributed to specific actors for accountability and compliance purposes.
            </p>
        </section>
    </div>
</x-filament-panels::page>
