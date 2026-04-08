<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-purple-600 to-pink-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-purple-100">System Health</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Platform Health & Metrics</h1>
            <p class="mt-2 max-w-2xl text-sm text-purple-100 sm:text-base">
                Real-time system performance, infrastructure health, and business metrics dashboards.
            </p>
        </section>

        <!-- System Metrics -->
        @php
            $systemMetrics = $this->getSystemMetrics();
        @endphp
        <section class="space-y-3">
            <h2 class="text-sm font-semibold text-slate-900">System Performance</h2>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-xl border border-green-200 bg-green-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-green-700">Uptime</p>
                    <p class="mt-1 text-2xl font-semibold text-green-900">{{ $systemMetrics['uptime'] ?? 0 }}%</p>
                    <p class="mt-1 text-xs text-green-700">Last 30 days</p>
                </div>

                <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-blue-700">API Response Time</p>
                    <p class="mt-1 text-2xl font-semibold text-blue-900">{{ $systemMetrics['api_response_time'] ?? '-' }}</p>
                    <p class="mt-1 text-xs text-blue-700">Average latency</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-700">DB Connections</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $systemMetrics['database_connections'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-700">Active pools</p>
                </div>

                <div class="rounded-xl border border-orange-200 bg-orange-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-orange-700">Queue Jobs</p>
                    <p class="mt-1 text-2xl font-semibold text-orange-900">{{ $systemMetrics['queue_jobs_pending'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-orange-700">Pending</p>
                </div>

                <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-indigo-700">Cache Hit Rate</p>
                    <p class="mt-1 text-2xl font-semibold text-indigo-900">{{ $systemMetrics['cache_hit_rate'] ?? 0 }}%</p>
                    <p class="mt-1 text-xs text-indigo-700">Optimization</p>
                </div>

                <div class="rounded-xl border border-red-200 bg-red-50 p-4">
                    <p class="text-xs uppercase tracking-wide text-red-700">Error Rate</p>
                    <p class="mt-1 text-2xl font-semibold text-red-900">{{ $systemMetrics['error_rate'] ?? 0 }}%</p>
                    <p class="mt-1 text-xs text-red-700">Incidents</p>
                </div>
            </div>
        </section>

        <!-- Business Metrics -->
        @php
            $businessMetrics = $this->getBusinessMetrics();
        @endphp
        <section class="space-y-3">
            <h2 class="text-sm font-semibold text-slate-900">Business Metrics (Today)</h2>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Total Rides</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $businessMetrics['total_rides_today'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-600">Completed today</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Total Revenue</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $businessMetrics['total_revenue'] ?? 'RWF 0' }}</p>
                    <p class="mt-1 text-xs text-slate-600">Today's earnings</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Active Drivers</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $businessMetrics['active_drivers'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-600">Online now</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Active Passengers</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $businessMetrics['active_passengers'] ?? 0 }}</p>
                    <p class="mt-1 text-xs text-slate-600">Using service now</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Average Rating</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $businessMetrics['average_rating'] ?? 0 }} ⭐</p>
                    <p class="mt-1 text-xs text-slate-600">Service quality</p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Completion Rate</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $businessMetrics['completion_rate'] ?? 0 }}%</p>
                    <p class="mt-1 text-xs text-slate-600">Success</p>
                </div>
            </div>
        </section>

        <!-- Health Status Card -->
        <section class="rounded-xl border border-green-200 bg-green-50 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="font-semibold text-green-900">✓ System Status: Healthy</p>
                    <p class="mt-1 text-xs text-green-700">All systems operational. No critical alerts.</p>
                </div>
                <span class="h-3 w-3 rounded-full bg-green-500 animate-pulse"></span>
            </div>
        </section>
    </div>
</x-filament-panels::page>
