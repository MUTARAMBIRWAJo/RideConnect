<div class="fi-section rounded-2xl p-4 sm:p-5">
  <div wire:loading class="space-y-3 animate-pulse">
    <div class="h-4 w-36 rounded bg-gray-200 dark:bg-gray-700"></div>
    <div class="h-52 rounded-xl bg-gray-200 dark:bg-gray-700"></div>
  </div>

  <div wire:loading.remove>
  <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
    <div>
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Driver Availability</h3>
      <div class="text-xs text-gray-500 dark:text-gray-300">Real-time driver status distribution</div>
    </div>
    <div class="text-xs text-gray-500">Total: {{ ($available ?? 0) + ($busy ?? 0) + ($offline ?? 0) }}</div>
  </div>

  @php($donutId = 'driverAvailabilityDonut-' . uniqid())
  <div class="flex flex-col gap-5 lg:flex-row lg:items-center">
    <div class="mx-auto h-[220px] w-full max-w-[260px] sm:h-[250px] lg:mx-0">
      <canvas id="{{ $donutId }}" class="h-full w-full"></canvas>
    </div>
    <div class="flex-1">
      <div class="space-y-3">
        <div class="flex items-center justify-between"><div class="text-xs">Available</div><div class="text-xs font-medium text-gray-800 dark:text-gray-200">{{ $available ?? 0 }}</div></div>
        <div class="flex items-center justify-between"><div class="text-xs">Busy</div><div class="text-xs font-medium text-blue-600">{{ $busy ?? 0 }}</div></div>
        <div class="flex items-center justify-between"><div class="text-xs">Offline</div><div class="text-xs font-medium text-gray-500">{{ $offline ?? 0 }}</div></div>
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
  <div class="mt-6">
    <h4 class="text-sm font-semibold mb-2">Available Drivers</h4>
    @if(isset($availableDrivers) && $availableDrivers->count())
      <div class="overflow-x-auto">
        <table class="min-w-full text-xs border rounded">
          <thead>
            <tr class="bg-gray-100 dark:bg-gray-800">
              <th class="px-2 py-1 text-left">Name</th>
              <th class="px-2 py-1 text-left">Vehicle Plate</th>
              <th class="px-2 py-1 text-left">Location</th>
            </tr>
          </thead>
          <tbody>
            @foreach($availableDrivers as $driver)
              <tr class="border-b">
                <td class="px-2 py-1">{{ $driver->user?->name ?? 'N/A' }}</td>
                <td class="px-2 py-1">{{ $driver->license_plate ?? ($driver->vehicles->first()?->license_plate ?? 'N/A') }}</td>
                <td class="px-2 py-1">
                  @if($driver->current_latitude && $driver->current_longitude)
                    {{ number_format($driver->current_latitude, 5) }}, {{ number_format($driver->current_longitude, 5) }}
                  @else
                    N/A
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <div class="text-xs text-gray-500">No available drivers found.</div>
    @endif
  </div>
</div>
