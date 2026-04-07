<x-filament-widgets::widget>
    <div class="space-y-4">
        <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Support Ticket Trends</h3>
        
        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Open Tickets -->
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-900/20">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-red-700 dark:text-red-300">Open Tickets</p>
                        <p class="mt-2 text-3xl font-bold text-red-600">{{ $openTickets }}</p>
                        <p class="text-xs text-red-500 dark:text-red-400">Requiring attention</p>
                    </div>
                    <svg class="h-12 w-12 text-red-200 dark:text-red-900" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 9.5c0 .83-.67 1.5-1.5 1.5S11 13.33 11 12.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5z"/>
                    </svg>
                </div>
            </div>

            <!-- Resolved Today -->
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-900/20">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-green-700 dark:text-green-300">Resolved Today</p>
                        <p class="mt-2 text-3xl font-bold text-green-600">{{ $resolvedToday }}</p>
                        <p class="text-xs text-green-500 dark:text-green-400">Tickets closed</p>
                    </div>
                    <svg class="h-12 w-12 text-green-200 dark:text-green-900" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>
                    </svg>
                </div>
            </div>

            <!-- Avg Resolution Time -->
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-900/20">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-blue-700 dark:text-blue-300">Avg Resolution Time</p>
                        <p class="mt-2 text-3xl font-bold text-blue-600">{{ $avgResolutionHours }}</p>
                        <p class="text-xs text-blue-500 dark:text-blue-400">Hours (30 days)</p>
                    </div>
                    <svg class="h-12 w-12 text-blue-200 dark:text-blue-900" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M11.99 5V1h-1v4zm6.93 2.5l2.83-2.83-1.41-1.41-2.83 2.83zm2.83 1.5H23v-1h-4v1zM17 11c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6-2.69-6-6-6zm0 10c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm.5-9H16v5h1.5z"/>
                    </svg>
                </div>
            </div>

            <!-- Tickets by Priority -->
            <div class="rounded-lg border border-purple-200 bg-purple-50 p-4 dark:border-purple-900 dark:bg-purple-900/20">
                <div class="space-y-2">
                    <p class="text-sm font-medium text-purple-700 dark:text-purple-300">Priority Breakdown</p>
                    @foreach(['HIGH' => 'high', 'MEDIUM' => 'medium', 'LOW' => 'low'] as $label => $key)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-300">{{ $label }}</span>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $byPriority[$label] ?? 0 }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
