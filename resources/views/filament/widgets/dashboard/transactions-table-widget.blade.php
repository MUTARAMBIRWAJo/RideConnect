<div class="fi-section p-6 rounded-2xl">
    <div class="flex items-center justify-between mb-5">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Transactions Table</h3>
        <span class="text-xs font-medium tracking-wide uppercase text-gray-500">Latest 10</span>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
        <table class="w-full table-auto text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">
                    <th class="px-3 py-2">ID</th>
                    <th class="px-3 py-2">Amount</th>
                    <th class="px-3 py-2">Status</th>
                    <th class="px-3 py-2">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse($transactions as $transaction)
                    <tr>
                        <td class="px-3 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $transaction->id ?? '-' }}</td>
                        <td class="px-3 py-3 font-semibold text-gray-900 dark:text-gray-100">RWF {{ number_format((float)($transaction->{$amountColumn} ?? 0), 2) }}</td>
                        <td class="px-3 py-3 text-gray-700 dark:text-gray-200">{{ $transaction->status ?? 'N/A' }}</td>
                        <td class="px-3 py-3 text-gray-600 dark:text-gray-300">{{ $transaction->created_at ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-sm text-gray-500">No transactions available.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
