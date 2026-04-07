<x-filament-widgets::widget>
    <div class="space-y-4">
        <h3 class="font-semibold text-lg text-gray-900 dark:text-white">Service Compliance</h3>
        
        <div class="grid gap-4 sm:grid-cols-2">
            <!-- Cancellation Rate -->
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Cancellation Rate</p>
                        <p class="mt-2 text-2xl font-bold text-red-600">{{ $cancellationRate }}%</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">30-day average</p>
                    </div>
                    <div class="text-red-100 dark:text-red-900/30">
                        <svg class="h-12 w-12" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Compliance Score -->
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Compliance Score</p>
                        <p class="mt-2 text-2xl font-bold text-green-600">{{ $complianceScore }}%</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Based on cancellations</p>
                    </div>
                    <div class="text-green-100 dark:text-green-900/30">
                        <svg class="h-12 w-12" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Completed Rides -->
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Completed (30 days)</p>
                        <p class="mt-2 text-2xl font-bold text-blue-600">{{ $completedRides }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">System rides</p>
                    </div>
                    <div class="text-blue-100 dark:text-blue-900/30">
                        <svg class="h-12 w-12" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.3-1.3l-5.2 6.6L8 10.5 6.6 12l4.1 4.1 6.6-8.1z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Avg Driver Rating -->
            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Avg Driver Rating</p>
                        <p class="mt-2 text-2xl font-bold text-yellow-600">{{ $avgDriverRating }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Out of 5.0</p>
                    </div>
                    <div class="text-yellow-100 dark:text-yellow-900/30">
                        <svg class="h-12 w-12" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2l-2.81 6.63L2 9.24l5.46 4.73L5.82 21z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
