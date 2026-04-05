<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Recent Activity</x-slot>
        <x-slot name="description">Latest rides, bookings, and cancellations across the platform.</x-slot>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-800">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3 text-left">Type</th>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-950">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-100">{{ $item['type'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">#{{ $item['id'] }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-md px-2 py-1 text-xs font-medium {{ str_contains(strtolower($item['status']), 'cancel') ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' }}">
                                    {{ $item['status'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $item['created_at'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-gray-500 dark:text-gray-400">No recent activity available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
