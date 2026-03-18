<x-filament-widgets::widget>
    <x-filament::section>
        <div wire:loading class="space-y-3 animate-pulse">
            <div class="h-4 w-40 rounded bg-gray-200 dark:bg-gray-700"></div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div class="h-20 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-20 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-20 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <div class="h-48 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
        </div>

        <div wire:loading.remove>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Financial Matrix</h3>
                <p class="text-xs text-gray-500 dark:text-gray-300">Accountant-grade matrix for revenue flow, payouts, and platform take rate.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full border border-fuchsia-300 bg-fuchsia-50 px-2.5 py-1 text-xs font-semibold text-fuchsia-800 dark:border-fuchsia-800 dark:bg-fuchsia-900/40 dark:text-fuchsia-300">
                    {{ $period['from'] ?? '-' }} to {{ $period['to'] ?? '-' }}
                </span>
                <form method="GET" action="{{ route('admin.exports.financial-matrix.csv') }}" class="flex flex-wrap items-center gap-2">
                    <input type="date" name="from" value="{{ $period['from'] ?? now()->subDays(29)->toDateString() }}" class="rounded-md border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-900" />
                    <input type="date" name="to" value="{{ $period['to'] ?? now()->toDateString() }}" class="rounded-md border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-900" />
                    <button type="submit" class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">CSV</button>
                    <button type="submit" formaction="{{ route('admin.exports.financial-matrix.pdf') }}" class="inline-flex items-center rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-800 transition hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">PDF</button>
                    <button type="submit" formaction="{{ route('admin.exports.financial-matrix.xlsx') }}" class="inline-flex items-center rounded-lg border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-800 transition hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-300">XLSX</button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 mb-4">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-800 dark:bg-emerald-900/20">
                <div class="text-[11px] uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Gross Revenue</div>
                <div class="mt-1 text-xl font-semibold text-emerald-900 dark:text-emerald-100">RWF {{ number_format($matrix['gross_range'] ?? 0, 0) }}</div>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50/70 p-3 dark:border-sky-800 dark:bg-sky-900/20">
                <div class="text-[11px] uppercase tracking-wide text-sky-700 dark:text-sky-300">Gross 7 Days</div>
                <div class="mt-1 text-2xl font-semibold text-sky-900 dark:text-sky-100">RWF {{ number_format($matrix['gross_7d'] ?? 0, 0) }}</div>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50/70 p-3 dark:border-violet-800 dark:bg-violet-900/20">
                <div class="text-[11px] uppercase tracking-wide text-violet-700 dark:text-violet-300">Platform Commission</div>
                <div class="mt-1 text-xl font-semibold text-violet-900 dark:text-violet-100">RWF {{ number_format($matrix['commission_range'] ?? 0, 0) }}</div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                <div class="text-[11px] uppercase tracking-wide text-amber-700 dark:text-amber-300">Driver Payouts</div>
                <div class="mt-1 text-xl font-semibold text-amber-900 dark:text-amber-100">RWF {{ number_format($matrix['payouts_range'] ?? 0, 0) }}</div>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 dark:border-rose-800 dark:bg-rose-900/20">
                <div class="text-[11px] uppercase tracking-wide text-rose-700 dark:text-rose-300">Pending Payouts</div>
                <div class="mt-1 text-xl font-semibold text-rose-900 dark:text-rose-100">{{ number_format($matrix['pending_payouts'] ?? 0) }}</div>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-3 dark:border-indigo-800 dark:bg-indigo-900/20">
                <div class="text-[11px] uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Take Rate</div>
                <div class="mt-1 text-xl font-semibold text-indigo-900 dark:text-indigo-100">{{ number_format((float) ($matrix['take_rate'] ?? 0), 2) }}%</div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2 text-right">Gross</th>
                        <th class="px-3 py-2 text-right">Transactions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($dailyRows as $row)
                        <tr>
                            <td class="px-3 py-2 text-gray-800 dark:text-gray-100">{{ $row['day'] }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-gray-900 dark:text-gray-100">RWF {{ number_format((float) ($row['gross'] ?? 0), 0) }}</td>
                            <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-200">{{ number_format((int) ($row['txns'] ?? 0)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-3 py-5 text-center text-sm text-gray-500">No financial rows available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
