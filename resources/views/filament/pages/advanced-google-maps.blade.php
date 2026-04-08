<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-green-600 to-emerald-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-green-100">Live Operations</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Advanced Maps & Real-Time Tracking</h1>
            <p class="mt-2 max-w-2xl text-sm text-green-100 sm:text-base">
                Real-time vehicle tracking, demand heatmaps, driver density visualization, and service analytics.
            </p>
        </section>

        <!-- Map Analytics Summary -->
        @php
            $analytics = $this->getMapAnalytics();
        @endphp
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Active Vehicles</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $analytics['active_vehicles'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-slate-600">Currently on the road</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Active Rides</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $analytics['active_rides'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-slate-600">In progress</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Avg Wait Time</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $analytics['average_wait_time'] ?? '-' }}</p>
                <p class="mt-1 text-xs text-slate-600">Customer average</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">System Efficiency</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $analytics['system_efficiency'] ?? 0 }}%</p>
                <p class="mt-1 text-xs text-slate-600">Operational</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Coverage Area</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $analytics['coverage_percentage'] ?? 0 }}%</p>
                <p class="mt-1 text-xs text-slate-600">Geographic</p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs uppercase tracking-wide text-slate-500">Peak Zones</p>
                <p class="mt-1 text-sm font-medium text-slate-900">
                    {{ implode(', ', $analytics['peak_zones'] ?? []) }}
                </p>
                <p class="mt-1 text-xs text-slate-600">High demand</p>
            </div>
        </section>

        <!-- Map Display Section -->
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Interactive Map Views</h2>
            <p class="mt-1 text-xs text-slate-600">Toggle different visualization layers for operational insights.</p>

            <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <input type="checkbox" wire:model.live="showRealTimeTracking" class="h-4 w-4 rounded border-slate-300" />
                    <span class="text-xs font-medium text-slate-700">Real-Time Tracking</span>
                </label>

                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <input type="checkbox" wire:model.live="showDemandHeatmap" class="h-4 w-4 rounded border-slate-300" />
                    <span class="text-xs font-medium text-slate-700">Demand Heatmap</span>
                </label>

                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <input type="checkbox" wire:model.live="showDriverDensity" class="h-4 w-4 rounded border-slate-300" />
                    <span class="text-xs font-medium text-slate-700">Driver Density</span>
                </label>

                <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <input type="checkbox" wire:model.live="showIncidentMap" class="h-4 w-4 rounded border-slate-300" />
                    <span class="text-xs font-medium text-slate-700">Incidents</span>
                </label>
            </div>

            <div class="mt-4 rounded-lg border border-slate-300 bg-slate-100 p-6 text-center">
                <p class="text-sm text-slate-600">🗺️ Interactive Google Maps integration with real-time data feeds</p>
                <p class="mt-1 text-xs text-slate-500">Vehicle positions update every 5 seconds</p>
            </div>
        </section>

        <!-- Time Range Filter -->
        <section class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Historical Analytics</h2>
            <p class="mt-1 text-xs text-slate-600">View aggregated data and trends for selected time periods.</p>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['1h' => 'Last Hour', '24h' => 'Last 24h', '7d' => 'Last 7 Days', '30d' => 'Last 30 Days'] as $range => $label)
                    <button 
                        wire:click="$set('selectedTimeRange', '{{ $range }}')" 
                        class="rounded-lg {{ $selectedTimeRange === $range ? 'bg-green-600 text-white' : 'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }} px-3 py-2 text-xs font-medium transition"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
