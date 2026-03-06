<div class="space-y-6">
  <!-- Top Section -->
  <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
    <div>
      <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Dashboard</h1>
      <div class="text-sm text-gray-500 dark:text-gray-300">Real-time system performance overview</div>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <button class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold text-white bg-gradient-to-r from-green-800 to-green-700 shadow-sm transition-all duration-200 hover:from-green-700 hover:to-green-600 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-300 focus-visible:ring-offset-2 dark:focus-visible:ring-green-700 dark:focus-visible:ring-offset-gray-900">Export Report</button>
      <button class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-green-200 dark:border-green-800 text-sm font-medium text-gray-800 dark:text-gray-200 transition-all duration-200 hover:bg-green-50 dark:hover:bg-green-900/30 hover:border-green-300 dark:hover:border-green-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-300 focus-visible:ring-offset-2 dark:focus-visible:ring-green-700 dark:focus-visible:ring-offset-gray-900">Refresh</button>
      <a href="#" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium text-gray-800 dark:text-gray-200 transition-all duration-200 hover:bg-green-50 dark:hover:bg-green-900/30 hover:text-gray-900 dark:hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-300 focus-visible:ring-offset-2 dark:focus-visible:ring-green-700 dark:focus-visible:ring-offset-gray-900">View Analytics</a>
    </div>
  </div>

  <!-- KPI Grid -->
  <div class="grid grid-cols-12 gap-5">
    <div class="col-span-12 md:col-span-6 lg:col-span-3">
      <x-filament.kpi-card :label="'Active Rides'" :value="$activeRides ?? 0" :change="$delta" :trend="$sparkline" color="bg-green-700" :icon="'<svg class=\'w-5 h-5\' xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M3 3h18v18H3V3z\' /></svg>'" />
    </div>

    <div class="col-span-12 md:col-span-6 lg:col-span-3">
      <x-filament.kpi-card :label="'Drivers Online'" :value="$driversOnline ?? 0" :change="rand(0,12)" :trend="$sparkline" color="bg-green-500" :icon="'👤'" />
    </div>

    <div class="col-span-12 md:col-span-6 lg:col-span-3">
      <x-filament.kpi-card :label="'Avg Wait Time'" :value="($avgWait ? round($avgWait) . ' min' : '—')" :change="null" :trend="$sparkline" color="bg-amber-500" :icon="'⏱️'" />
    </div>

    <div class="col-span-12 md:col-span-6 lg:col-span-3">
      <x-filament.kpi-card :label="'Rides Today'" :value="$ridesToday ?? 0" :change="rand(1,20)" :trend="$sparkline" color="bg-green-600" :icon="'📈'" />
    </div>
  </div>

  <!-- Larger stats row (optional) -->
  <div class="grid grid-cols-12 gap-5">
    <div class="col-span-12 lg:col-span-8">
      <x-filament.stat-widget title="System Health" :value="'OK'" meta="Realtime checks — all nominal" :icon="'⚡'" />
    </div>
    <div class="col-span-12 lg:col-span-4">
      <x-filament.stat-widget title="Driver Utilization" :value="'72%'" meta="Last 24 hours" :icon="'🚗'" />
    </div>
  </div>
</div>
