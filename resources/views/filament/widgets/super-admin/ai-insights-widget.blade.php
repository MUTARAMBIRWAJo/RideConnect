<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">AI Insights</x-slot>
        <x-slot name="description">Demand, surge, and ETA predictions from the external AI service.</x-slot>

        <div class="space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Prediction Frequency</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ (int) ($eta['minutes'] ?? 0) }} min</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Confidence: {{ number_format(((float) ($eta['confidence'] ?? 0)) * 100, 1) }}%</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Predicted Busy Zones</p>
                    <p class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $busyHours !== [] ? implode(' • ', $busyHours) : 'No high-demand zones detected' }}
                    </p>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="border-b border-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        Demand Heatmap Levels
                    </div>
                    <div class="max-h-56 overflow-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2">Zone</th>
                                    <th class="px-4 py-2">Level</th>
                                    <th class="px-4 py-2">Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($demandZones as $zone)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="px-4 py-2 text-gray-800 dark:text-gray-100">{{ $zone['zone'] }}</td>
                                        <td class="px-4 py-2">
                                            <span class="rounded-md px-2 py-1 text-xs font-medium {{ strtoupper($zone['level']) === 'HIGH' ? 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300' }}">
                                                {{ $zone['level'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ number_format((float) $zone['score'] * 100, 1) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No demand insights available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 dark:border-gray-800">
                    <div class="border-b border-gray-200 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-gray-600 dark:border-gray-800 dark:text-gray-300">
                        Surge Multipliers
                    </div>
                    <div class="max-h-56 overflow-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-2">Zone</th>
                                    <th class="px-4 py-2">Multiplier</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($surgeZones as $zone)
                                    <tr class="border-t border-gray-100 dark:border-gray-800">
                                        <td class="px-4 py-2 text-gray-800 dark:text-gray-100">{{ $zone['zone'] }}</td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-300">x{{ number_format((float) $zone['multiplier'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">No surge predictions available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
