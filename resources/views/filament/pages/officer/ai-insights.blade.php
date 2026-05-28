<x-filament-panels::page>
    <div class="space-y-6" wire:poll.30s>
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-indigo-600 to-blue-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-indigo-100">AI & Analytics</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">AI Insights</h1>
            <p class="mt-2 max-w-2xl text-sm text-indigo-100 sm:text-base">
                AI-powered analytics for demand forecasting, peak hours, and operational optimization.
            </p>
        </section>

        <!-- Key Metrics -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <x-dashboard-card title="Avg Wait Time" :value="$avgWaitTime === null ? '—' : number_format($avgWaitTime, 2) . ' min'" subtitle="Platform average" tone="blue" />
            <x-dashboard-card title="Acceptance Rate" :value="$acceptanceRate === null ? '—' : number_format($acceptanceRate, 1) . '%'" subtitle="Driver acceptance" tone="green" />
        </section>

        <!-- ML Service Demand Prediction (Real-time from ML Microservice) -->
        <section class="rounded-xl border-2 border-purple-200 bg-gradient-to-br from-purple-50 to-purple-100 p-6 shadow-md">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-semibold text-purple-900">🤖 ML Service Demand Prediction</h2>
                    <p class="mt-1 text-xs text-purple-700">Real-time forecast from the deployed ML service</p>
                        <p class="mt-1 text-[11px] text-purple-600">
                            Backend: <span class="font-semibold">{{ $mlServiceUrl }}</span> · Endpoint: <span class="font-semibold">/ml/predict-demand</span>
                        </p>
                        <p class="mt-1 text-[11px] text-purple-500">
                            Health: <span class="font-semibold">{{ data_get($mlServiceHealth, 'success') ? 'healthy' : 'degraded' }}</span>
                            · Source: <span class="font-semibold">{{ ucfirst(str_replace('-', ' ', $mlDemandPrediction['source'] ?? 'unknown')) }}</span>
                        </p>
                </div>
                <div class="px-3 py-1 rounded-full text-xs font-medium @if ($demandPredictionStatus === 'success') bg-green-200 text-green-800 @elseif ($demandPredictionStatus === 'fallback') bg-yellow-200 text-yellow-800 @else bg-red-200 text-red-800 @endif">
                    @if ($demandPredictionStatus === 'success')
                        ✓ Live
                    @elseif ($demandPredictionStatus === 'fallback')
                        ↺ Fallback
                    @else
                        ✗ Error
                    @endif
                </div>
            </div>

            @if (!empty($mlDemandPrediction))
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Forecast Score -->
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Forecast Score</p>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-3xl font-bold text-purple-600">{{ number_format(($mlDemandPrediction['predicted_demand'] ?? 0) * 100, 1) }}%</span>
                        </div>
                        <div class="mt-3 w-full bg-slate-200 rounded-full h-2">
                            <div class="bg-gradient-to-r from-purple-400 to-purple-600 h-2 rounded-full" style="width: {{ ($mlDemandPrediction['predicted_demand'] ?? 0) * 100 }}%"></div>
                        </div>
                        <p class="mt-2 text-xs text-slate-600">
                            @if (($mlDemandPrediction['predicted_demand'] ?? 0) > 0.75)
                                Very High forecast
                            @elseif (($mlDemandPrediction['predicted_demand'] ?? 0) > 0.5)
                                High forecast
                            @elseif (($mlDemandPrediction['predicted_demand'] ?? 0) > 0.25)
                                Moderate forecast
                            @else
                                Low forecast
                            @endif
                        </p>
                    </div>

                    <!-- Input Payload -->
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Forecast Hour</p>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-3xl font-bold text-blue-600">{{ $mlDemandPrediction['input_payload']['hour'] ?? now('Africa/Kigali')->hour }}</span>
                            <span class="text-lg text-slate-600">h</span>
                        </div>
                        <p class="mt-3 text-xs text-slate-600">
                            Live contract payload sent to the deployed demand endpoint
                        </p>
                    </div>

                    <!-- Wait Time -->
                    <div class="bg-white rounded-lg p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-wide text-slate-500 font-semibold">Expected Wait</p>
                        <div class="mt-2 flex items-baseline gap-2">
                            <span class="text-3xl font-bold text-green-600">{{ $mlDemandPrediction['predicted_demand_raw']['expected_wait_time_minutes'] ?? '-' }}</span>
                            <span class="text-lg text-slate-600">min</span>
                        </div>
                        <div class="mt-3 w-full bg-slate-200 rounded-full h-2">
                            <div class="bg-gradient-to-r from-green-400 to-green-600 h-2 rounded-full" style="width: {{ ($mlDemandPrediction['predicted_demand_raw']['confidence'] ?? 0) * 100 }}%"></div>
                        </div>
                        <p class="mt-2 text-xs text-slate-600">Confidence {{ number_format(($mlDemandPrediction['predicted_demand_raw']['confidence'] ?? 0) * 100, 0) }}%</p>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-purple-200">
                    <p class="text-xs text-purple-700">
                        <strong>Last updated:</strong> {{ \Carbon\Carbon::parse($mlDemandPrediction['timestamp'])->diffForHumans() }}
                        <br>
                        <strong>Input:</strong> latitude/longitude/hour/day_of_week
                        @if (!empty($mlDemandPrediction['remote_error']))
                            <br>
                            <strong>ML service note:</strong> {{ $mlDemandPrediction['remote_error'] }}
                        @endif
                    </p>
                </div>
            @else
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-yellow-800">Loading demand prediction from ML service at {{ $mlServiceUrl }}...</p>
                </div>
            @endif
        </section>

        <!-- Demand by Area -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">📍 Demand Heatmap</h2>
            <p class="mt-1 text-xs text-slate-600">Current demand by service area with available driver supply.</p>
            <div class="mt-4 space-y-2">
                @forelse ($demandByArea as $area)
                    <div class="flex items-center gap-3">
                        <div class="min-w-24 text-sm font-medium text-slate-700">{{ $area['area'] }}</div>
                        <div class="flex-1">
                            <div class="h-6 bg-slate-100 rounded-lg overflow-hidden">
                                <div class="h-full bg-gradient-to-r from-blue-400 to-blue-600" style="width: {{ min(100, ($area['demand'] / 500) * 100) }}%"></div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-semibold text-slate-900">{{ $area['demand'] }} requests</div>
                            <div class="text-xs text-slate-600">{{ is_null($area['available_drivers'] ?? null) ? '—' : $area['available_drivers'].' drivers' }}</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 text-center py-4">No demand data available.</p>
                @endforelse
            </div>
        </section>

        <!-- Peak Hours -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">⏰ Today's Peak Hours</h2>
            <p class="mt-1 text-xs text-slate-600">Expected surge times and demand levels.</p>
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                @forelse ($peakHours as $peak)
                    <div class="rounded-lg border-2 @if ($peak['color'] === 'red') border-red-200 bg-red-50 @elseif ($peak['color'] === 'orange') border-orange-200 bg-orange-50 @else border-yellow-200 bg-yellow-50 @endif p-4">
                        <div class="font-semibold @if ($peak['color'] === 'red') text-red-900 @elseif ($peak['color'] === 'orange') text-orange-900 @else text-yellow-900 @endif">
                            {{ $peak['hour'] }}
                        </div>
                        <div class="text-xs @if ($peak['color'] === 'red') text-red-700 @elseif ($peak['color'] === 'orange') text-orange-700 @else text-yellow-700 @endif font-medium mt-1">
                            {{ ucfirst($peak['demand']) }} Demand
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 col-span-2 text-center py-4">No peak hour data.</p>
                @endforelse
            </div>
        </section>

        <!-- Weekly Trend -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">📊 Weekly Trend</h2>
            <p class="mt-1 text-xs text-slate-600">Rides completed and revenue generated by day.</p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 font-semibold">
                            <th class="py-2 pr-3">Day</th>
                            <th class="py-2 pr-3">Rides</th>
                            <th class="py-2">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($trendData as $day)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-3 font-medium text-slate-900">{{ $day['date'] }}</td>
                                <td class="py-2 pr-3">
                                    <span class="inline-flex items-center gap-1">
                                        <div class="h-1 bg-blue-400 rounded-full" style="width: {{ ($day['rides'] / 500) * 40 }}px"></div>
                                        {{ $day['rides'] }}
                                    </span>
                                </td>
                                <td class="py-2 font-semibold text-slate-900">${{ number_format($day['revenue'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center text-slate-500 text-sm">No trend data.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Recommendations -->
        <section class="rounded-xl border border-green-200 bg-green-50 p-4">
            <p class="text-xs text-green-900">
                💡 <strong>AI Recommendation:</strong> Based on demand forecasts, consider 
                increasing driver incentives during peak hours (6-9 AM, 6-9 PM) to improve acceptance rates.
                Current wait times are acceptable; monitor demand in Downtown area closely.
            </p>
        </section>
    </div>
</x-filament-panels::page>
