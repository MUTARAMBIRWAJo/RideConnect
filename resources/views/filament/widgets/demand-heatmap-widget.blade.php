<div class="fi-section p-6 rounded-2xl">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-white">AI Demand Heatmap — Kigali</h3>
      <div class="text-sm text-gray-500 dark:text-gray-300">Predicted demand in next 30 minutes</div>
    </div>
    <div class="flex items-center gap-2 text-xs text-gray-500">
      <span class="w-3 h-3 rounded-full bg-blue-100 inline-block"></span> Low
      <span class="w-3 h-3 rounded-full bg-amber-100 inline-block ml-3"></span> Medium
      <span class="w-3 h-3 rounded-full bg-red-100 inline-block ml-3"></span> High
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-gradient-to-br from-gray-50 to-white dark:from-gray-800 dark:to-gray-800 rounded-lg overflow-hidden" style="height:320px">
      <div class="relative h-full">
        <div class="absolute inset-0 grid grid-cols-8 grid-rows-6 opacity-10" style="background-image: linear-gradient(transparent 1px, rgba(16,185,129,0.06) 1px); background-size: 32px 32px"></div>
        <div class="absolute inset-0 flex items-center justify-center">
          <div class="text-gray-400 dark:text-gray-500">[Map placeholder — integrate Mapbox/Leaflet]</div>
        </div>
        @foreach($markers as $m)
          <div class="absolute" style="left:{{ rand(20,80) }}%; top:{{ rand(15,75) }}%">
            <div class="flex items-center space-x-2">
              <span class="w-3 h-3 rounded-full bg-gradient-to-br from-green-400 to-green-300 animate-pulse block"></span>
              <span class="text-sm text-gray-700 dark:text-gray-300 font-medium">{{ $m['label'] }}</span>
            </div>
          </div>
        @endforeach
      </div>
    </div>

    <div class="space-y-4">
      <div class="fi-section p-4 rounded-lg">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white">Driver Availability</h4>
        <div class="mt-3">
          <canvas id="driverAvailabilityDonutSmall" style="max-width:220px"></canvas>
        </div>
      </div>

      <div class="fi-section p-4 rounded-lg">
        <h4 class="text-sm font-medium text-gray-900 dark:text-white">Legend</h4>
        <div class="mt-2 text-sm text-gray-500 dark:text-gray-300">Low → High demand gradient (blue → amber → red). Heat intensity driven by AI predictions.</div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    (function(){
      const ctx = document.getElementById('driverAvailabilityDonutSmall');
      if(!ctx) return;
      try{
        new Chart(ctx.getContext('2d'), {
          type: 'doughnut',
          data: { labels:['Available','Busy','Offline'], datasets:[{ data:[{{ $available ?? 0 }}, {{ $busy ?? 0 }}, {{ $offline ?? 0 }}], backgroundColor:['var(--color-success)','var(--color-primary)','var(--color-muted)'] }]},
          options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{position:'bottom'}} }
        });
      }catch(e){}
    })();
  </script>
</div>
