<div class="fi-section rounded-2xl p-4 sm:p-6">
  <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Driver Availability</h3>
      <div class="text-sm text-gray-500 dark:text-gray-300">Real-time driver status distribution</div>
    </div>
    <div class="text-sm text-gray-500">Total: {{ ($available ?? 0) + ($busy ?? 0) + ($offline ?? 0) }}</div>
  </div>

  @php($donutId = 'driverAvailabilityDonut-' . uniqid())
  <div class="flex flex-col gap-5 lg:flex-row lg:items-center">
    <div class="mx-auto h-[220px] w-full max-w-[260px] sm:h-[250px] lg:mx-0">
      <canvas id="{{ $donutId }}" class="h-full w-full"></canvas>
    </div>
    <div class="flex-1">
      <div class="space-y-3">
        <div class="flex items-center justify-between"><div class="text-sm">Available</div><div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $available ?? 0 }}</div></div>
        <div class="flex items-center justify-between"><div class="text-sm">Busy</div><div class="text-sm font-medium text-blue-600">{{ $busy ?? 0 }}</div></div>
        <div class="flex items-center justify-between"><div class="text-sm">Offline</div><div class="text-sm font-medium text-gray-500">{{ $offline ?? 0 }}</div></div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    (function(){
      const chartId = '{{ $donutId }}';
      const ctx = document.getElementById(chartId);
      if(!ctx) return;
      try{
        window.__rideConnectCharts = window.__rideConnectCharts || {};
        if (window.__rideConnectCharts[chartId]) {
          window.__rideConnectCharts[chartId].destroy();
        }

        window.__rideConnectCharts[chartId] = new Chart(ctx.getContext('2d'), {
          type: 'doughnut',
          data: { labels:['Available','Busy','Offline'], datasets:[{ data:[{{ $available ?? 0 }}, {{ $busy ?? 0 }}, {{ $offline ?? 0 }}], backgroundColor:['var(--color-success)','var(--color-primary)','var(--color-muted)'] }]},
          options: {
            responsive:true,
            maintainAspectRatio:false,
            plugins:{legend:{display:false}},
          }
        });
      }catch(e){}
    })();
  </script>
</div>
