@php($widgetId = 'ops-intel-' . uniqid())
@php($trendCanvasId = 'ops-trend-' . uniqid())
@php($statusCanvasId = 'ops-status-' . uniqid())

<x-filament-widgets::widget>
    <x-filament::section>
        <div wire:loading class="space-y-3 animate-pulse">
            <div class="h-4 w-48 rounded bg-gray-200 dark:bg-gray-700"></div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="h-20 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-20 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-20 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
                <div class="h-20 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <div class="h-56 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
        </div>

        <div wire:loading.remove>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Operations Intelligence</h3>
                <p class="text-xs text-gray-500 dark:text-gray-300">Live operational pulse with trend charts and route pressure hotspots.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center rounded-full border border-cyan-300 bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-800 dark:border-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-300">
                    Refreshed by polling
                </span>
                <form method="GET" action="{{ route('admin.exports.operations-intelligence.csv') }}" class="flex flex-wrap items-center gap-2">
                    <input type="date" name="from" value="{{ $exportFrom }}" class="rounded-md border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-900" />
                    <input type="date" name="to" value="{{ $exportTo }}" class="rounded-md border border-gray-300 px-2 py-1 text-xs dark:border-gray-700 dark:bg-gray-900" />
                    <button type="submit" class="inline-flex items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 transition hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">CSV</button>
                    <button type="submit" formaction="{{ route('admin.exports.operations-intelligence.pdf') }}" class="inline-flex items-center rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-800 transition hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300">PDF</button>
                    <button type="submit" formaction="{{ route('admin.exports.operations-intelligence.xlsx') }}" class="inline-flex items-center rounded-lg border border-sky-300 bg-sky-50 px-3 py-1.5 text-xs font-semibold text-sky-800 transition hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-900/30 dark:text-sky-300">XLSX</button>
                </form>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4 mb-4">
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-800 dark:bg-emerald-900/20">
                <div class="text-[11px] uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Drivers Online</div>
                <div class="mt-1 text-2xl font-semibold text-emerald-900 dark:text-emerald-100">{{ number_format($kpis['drivers_online'] ?? 0) }}</div>
            </div>
            <div class="rounded-xl border border-sky-200 bg-sky-50/70 p-3 dark:border-sky-800 dark:bg-sky-900/20">
                <div class="text-[11px] uppercase tracking-wide text-sky-700 dark:text-sky-300">Rides In Progress</div>
                <div class="mt-1 text-2xl font-semibold text-sky-900 dark:text-sky-100">{{ number_format($kpis['rides_in_progress'] ?? 0) }}</div>
            </div>
            <div class="rounded-xl border border-indigo-200 bg-indigo-50/70 p-3 dark:border-indigo-800 dark:bg-indigo-900/20">
                <div class="text-[11px] uppercase tracking-wide text-indigo-700 dark:text-indigo-300">Completed Today</div>
                <div class="mt-1 text-2xl font-semibold text-indigo-900 dark:text-indigo-100">{{ number_format($kpis['rides_completed_today'] ?? 0) }}</div>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-800 dark:bg-amber-900/20">
                <div class="text-[11px] uppercase tracking-wide text-amber-700 dark:text-amber-300">Bookings Pending</div>
                <div class="mt-1 text-2xl font-semibold text-amber-900 dark:text-amber-100">{{ number_format($kpis['bookings_pending'] ?? 0) }}</div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-4" id="{{ $widgetId }}" data-trend='@json($dailyTrend)' data-status='@json($statusMix)'>
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-900/40 p-3 xl:col-span-2">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200 mb-2">7-Day Ride Trend</h4>
                <canvas id="{{ $trendCanvasId }}" height="110"></canvas>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-900/40 p-3">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200 mb-2">Ride Status Mix</h4>
                <canvas id="{{ $statusCanvasId }}" height="110"></canvas>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-900/40 p-3 xl:col-span-2">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200 mb-2">Top Demand Routes</h4>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-500 dark:text-gray-300">
                                <th class="py-2 pr-3">From</th>
                                <th class="py-2 pr-3">To</th>
                                <th class="py-2 pr-1 text-right">Trips</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse($topRoutes as $route)
                                <tr>
                                    <td class="py-2 pr-3 text-gray-800 dark:text-gray-100">{{ $route['from'] }}</td>
                                    <td class="py-2 pr-3 text-gray-700 dark:text-gray-200">{{ $route['to'] }}</td>
                                    <td class="py-2 pr-1 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($route['total']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-5 text-center text-sm text-gray-500">No route movement data yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white/80 dark:bg-gray-900/40 p-3">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-700 dark:text-gray-200 mb-2">Trip Lifecycle Diagram</h4>
                <svg viewBox="0 0 260 170" class="w-full h-[170px]">
                    <defs>
                        <linearGradient id="ops-grad" x1="0" x2="1">
                            <stop offset="0%" stop-color="#38bdf8" />
                            <stop offset="100%" stop-color="#0ea5e9" />
                        </linearGradient>
                    </defs>
                    <rect x="8" y="18" width="78" height="34" rx="8" fill="#e0f2fe" stroke="#7dd3fc" />
                    <text x="47" y="39" text-anchor="middle" font-size="10" fill="#0c4a6e">Requested</text>

                    <rect x="92" y="18" width="78" height="34" rx="8" fill="#dcfce7" stroke="#86efac" />
                    <text x="131" y="39" text-anchor="middle" font-size="10" fill="#14532d">Accepted</text>

                    <rect x="176" y="18" width="76" height="34" rx="8" fill="#fef3c7" stroke="#fcd34d" />
                    <text x="214" y="39" text-anchor="middle" font-size="10" fill="#78350f">In Progress</text>

                    <rect x="92" y="112" width="78" height="34" rx="8" fill="#ede9fe" stroke="#c4b5fd" />
                    <text x="131" y="133" text-anchor="middle" font-size="10" fill="#4c1d95">Completed</text>

                    <rect x="8" y="112" width="78" height="34" rx="8" fill="#fee2e2" stroke="#fca5a5" />
                    <text x="47" y="133" text-anchor="middle" font-size="10" fill="#7f1d1d">Cancelled</text>

                    <path d="M86 35 L92 35" stroke="url(#ops-grad)" stroke-width="2" marker-end="url(#arrow)" />
                    <path d="M170 35 L176 35" stroke="url(#ops-grad)" stroke-width="2" marker-end="url(#arrow)" />
                    <path d="M214 52 L214 96 L170 96 L170 112" stroke="#8b5cf6" stroke-width="2" fill="none" marker-end="url(#arrow)" />
                    <path d="M131 52 L131 112" stroke="#10b981" stroke-width="2" marker-end="url(#arrow)" />
                    <path d="M47 52 L47 112" stroke="#ef4444" stroke-width="2" marker-end="url(#arrow)" />

                    <defs>
                        <marker id="arrow" markerWidth="6" markerHeight="6" refX="5" refY="3" orient="auto">
                            <path d="M0,0 L6,3 L0,6 z" fill="#0284c7" />
                        </marker>
                    </defs>
                </svg>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (function () {
                const container = document.getElementById('{{ $widgetId }}');
                if (!container || container.dataset.initialized === '1') {
                    return;
                }
                container.dataset.initialized = '1';

                const trend = JSON.parse(container.dataset.trend || '{"labels":[],"values":[]}');
                const status = JSON.parse(container.dataset.status || '{"labels":[],"values":[]}');

                const trendCanvas = document.getElementById('{{ $trendCanvasId }}');
                const statusCanvas = document.getElementById('{{ $statusCanvasId }}');

                if (trendCanvas) {
                    new Chart(trendCanvas.getContext('2d'), {
                        type: 'line',
                        data: {
                            labels: trend.labels || [],
                            datasets: [{
                                label: 'Rides',
                                data: trend.values || [],
                                borderColor: '#0284c7',
                                backgroundColor: 'rgba(2,132,199,0.15)',
                                fill: true,
                                tension: 0.35,
                                borderWidth: 2,
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, ticks: { precision: 0 } },
                            },
                        },
                    });
                }

                if (statusCanvas) {
                    new Chart(statusCanvas.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: status.labels || [],
                            datasets: [{
                                data: status.values || [],
                                backgroundColor: ['#f59e0b', '#22c55e', '#0ea5e9', '#8b5cf6', '#ef4444'],
                            }],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { position: 'bottom' } },
                        },
                    });
                }
            })();
        </script>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
