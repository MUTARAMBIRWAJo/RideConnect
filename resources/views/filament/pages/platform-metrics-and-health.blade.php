<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-purple-600 via-violet-600 to-pink-600 p-6 text-white shadow-xl">
            <div class="flex items-center gap-3 mb-2">
                <x-heroicon-o-cpu-chip class="w-6 h-6 text-purple-200" />
                <p class="text-xs font-semibold uppercase tracking-wider text-purple-100">System Health</p>
            </div>
            <h1 class="text-2xl font-bold sm:text-3xl">Platform Health & Metrics</h1>
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
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div class="group rounded-xl border border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-4 sm:p-5 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-green-800 dark:bg-gradient-to-br dark:from-green-900 dark:to-emerald-900 dark:text-green-100">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs uppercase tracking-wide text-green-700 dark:text-green-300 font-semibold">Uptime</p>
                        <div class="p-2 rounded-lg bg-green-100 dark:bg-green-800">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-600 dark:text-green-400" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-green-900 dark:text-green-100">{{ $systemMetrics['uptime'] ?? 0 }}%</p>
                    <p class="mt-2 text-xs text-green-700 dark:text-green-300">Last 30 days</p>
                </div>

                <div class="group rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-cyan-50 p-4 sm:p-5 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-blue-800 dark:bg-gradient-to-br dark:from-blue-900 dark:to-cyan-900 dark:text-blue-100">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs uppercase tracking-wide text-blue-700 dark:text-blue-300 font-semibold">API Response Time</p>
                        <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-800">
                            <x-heroicon-o-clock class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-blue-900 dark:text-blue-100">{{ $systemMetrics['api_response_time'] ?? '-' }}</p>
                    <p class="mt-2 text-xs text-blue-700 dark:text-blue-300">Average latency</p>
                </div>

                <div class="group rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-gray-50 p-4 sm:p-5 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-slate-700 dark:bg-gradient-to-br dark:from-slate-900 dark:to-gray-900 dark:text-slate-100">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs uppercase tracking-wide text-slate-700 dark:text-slate-300 font-semibold">DB Connections</p>
                        <div class="p-2 rounded-lg bg-slate-100 dark:bg-slate-800">
                            <x-heroicon-o-server-stack class="w-4 h-4 text-slate-600 dark:text-slate-400" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-slate-900 dark:text-slate-100">{{ $systemMetrics['database_connections'] ?? 0 }}</p>
                    <p class="mt-2 text-xs text-slate-700 dark:text-slate-300">Active pools</p>
                </div>

                <div class="group rounded-xl border border-orange-200 bg-gradient-to-br from-orange-50 to-amber-50 p-4 sm:p-5 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-orange-800 dark:bg-gradient-to-br dark:from-orange-900 dark:to-amber-900 dark:text-orange-100">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs uppercase tracking-wide text-orange-700 dark:text-orange-300 font-semibold">Queue Jobs</p>
                        <div class="p-2 rounded-lg bg-orange-100 dark:bg-orange-800">
                            <x-heroicon-o-queue-list class="w-4 h-4 text-orange-600 dark:text-orange-400" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-orange-900 dark:text-orange-100">{{ $systemMetrics['queue_jobs_pending'] ?? 0 }}</p>
                    <p class="mt-2 text-xs text-orange-700 dark:text-orange-300">Pending</p>
                </div>

                <div class="group rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-purple-50 p-4 sm:p-5 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-indigo-800 dark:bg-gradient-to-br dark:from-indigo-900 dark:to-purple-900 dark:text-indigo-100">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs uppercase tracking-wide text-indigo-700 dark:text-indigo-300 font-semibold">Cache Hit Rate</p>
                        <div class="p-2 rounded-lg bg-indigo-100 dark:bg-indigo-800">
                            <x-heroicon-o-bolt class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-indigo-900 dark:text-indigo-100">{{ $systemMetrics['cache_hit_rate'] ?? 0 }}%</p>
                    <p class="mt-2 text-xs text-indigo-700 dark:text-indigo-300">Optimization</p>
                </div>

                <div class="group rounded-xl border border-red-200 bg-gradient-to-br from-red-50 to-pink-50 p-4 sm:p-5 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-red-800 dark:bg-gradient-to-br dark:from-red-900 dark:to-pink-900 dark:text-red-100">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-xs uppercase tracking-wide text-red-700 dark:text-red-300 font-semibold">Error Rate</p>
                        <div class="p-2 rounded-lg bg-red-100 dark:bg-red-800">
                            <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-red-600 dark:text-red-400" />
                        </div>
                    </div>
                    <p class="text-3xl font-bold text-red-900 dark:text-red-100">{{ $systemMetrics['error_rate'] ?? 0 }}%</p>
                    <p class="mt-2 text-xs text-red-700 dark:text-red-300">Last 24 hours</p>
                </div>
            </div>
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
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-3">
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
