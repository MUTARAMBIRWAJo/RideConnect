<div class="fi-section p-6 rounded-2xl">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Driver Availability</h3>
      <div class="text-sm text-gray-500 dark:text-gray-300">Real-time driver status distribution</div>
    </div>
    <div class="text-sm text-gray-500">Total: {{ ($available ?? 0) + ($busy ?? 0) + ($offline ?? 0) }}</div>
  </div>

  <div class="flex items-center gap-6">
    <div style="width:260px; height:260px;">
      <canvas id="driverAvailabilityDonut" style="max-width:260px"></canvas>
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
      const ctx = document.getElementById('driverAvailabilityDonut');
      if(!ctx) return;
      try{
        new Chart(ctx.getContext('2d'), {
          type: 'doughnut',
          data: { labels:['Available','Busy','Offline'], datasets:[{ data:[{{ $available ?? 0 }}, {{ $busy ?? 0 }}, {{ $offline ?? 0 }}], backgroundColor:['var(--color-success)','var(--color-primary)','var(--color-muted)'] }]},
          options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }
        });
      }catch(e){}
    })();
  </script>
</div>
