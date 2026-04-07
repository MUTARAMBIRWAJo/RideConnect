<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-blue-600 to-cyan-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-blue-100">Live Monitoring</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Live Rides</h1>
            <p class="mt-2 max-w-2xl text-sm text-blue-100 sm:text-base">
                Real-time tracking of all active rides. Monitor location, driver, passenger, and optimize dispatch.
            </p>
        </section>

        <!-- Stats Row -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-dashboard-card title="Total Active" :value="number_format($totalActiveCount)" subtitle="Currently in progress" tone="blue" />
            <x-dashboard-card title="Avg Duration" :value="'18 min'" subtitle="Average ride time" tone="indigo" />
            <x-dashboard-card title="Platform Load" :value="'72%'" subtitle="Network utilization" tone="purple" />
        </section>

        <!-- Active Rides Table -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Active Rides Monitoring</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 font-semibold">
                            <th class="py-3 pr-3">Ride ID</th>
                            <th class="py-3 pr-3">Route</th>
                            <th class="py-3 pr-3">Status</th>
                            <th class="py-3 pr-3">Driver</th>
                            <th class="py-3 pr-3">Distance</th>
                            <th class="py-3 pr-3">Est. Fare</th>
                            <th class="py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($activeRides as $ride)
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                                <td class="py-3 pr-3 font-mono text-blue-600">#{{ $ride['id'] ?? '-' }}</td>
                                <td class="py-3 pr-3 text-xs">
                                    <div class="text-slate-900 font-medium">{{ substr($ride['origin_address'] ?? 'Unknown', 0, 20) }}</div>
                                    <div class="text-slate-500">→ {{ substr($ride['destination_address'] ?? 'Unknown', 0, 20) }}</div>
                                </td>
                                <td class="py-3 pr-3">
                                    <span class="inline-block rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">
                                        {{ strtoupper($ride['status'] ?? '-') }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3 text-slate-700">Driver #{{ $ride['driver_id'] ?? 'N/A' }}</td>
                                <td class="py-3 pr-3 text-slate-700">{{ $ride['distance'] ?? '0' }} km</td>
                                <td class="py-3 pr-3 font-semibold text-slate-900">{{ isset($ride['estimated_fare']) ? '$' . number_format($ride['estimated_fare'], 2) : 'N/A' }}</td>
                                <td class="py-3">
                                    <div class="flex gap-2">
                                        <button class="text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded hover:bg-orange-200 transition">Reassign</button>
                                        <button class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200 transition">Cancel</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-500 text-sm">No active rides at this moment.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Legend & Notes -->
        <section class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-medium text-slate-700">
                💡 <strong>Tip:</strong> Use Reassign to manually match rides to drivers. Use Cancel only for emergencies. 
                Platform automatically optimizes matching.
            </p>
        </section>
    </div>
</x-filament-panels::page>
