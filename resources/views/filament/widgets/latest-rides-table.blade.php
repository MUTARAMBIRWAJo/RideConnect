<div class="fi-section p-6 rounded-2xl">
  <div class="flex items-center justify-between mb-5">
    <div>
      <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Latest Rides</h3>
      <div class="text-sm text-gray-500 dark:text-gray-300">Most recent activity — compact view</div>
    </div>
    <div class="text-xs font-medium tracking-wide uppercase text-gray-500">Total: {{ $rides->count() }}</div>
  </div>

  <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
    <table class="w-full table-auto text-sm">
      <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-300">
          <th class="px-3 py-2">Ride</th>
          <th class="px-3 py-2">Driver</th>
          <th class="px-3 py-2">Route</th>
          <th class="px-3 py-2">Status</th>
          <th class="px-3 py-2">Requested</th>
          <th class="px-3 py-2">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        @forelse($rides as $ride)
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-900">
          <td class="px-3 py-3">
            <a href="#" class="text-sm font-semibold text-gray-800 dark:text-gray-200 transition-all duration-200 hover:underline hover:text-green-700 dark:hover:text-green-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-300 focus-visible:ring-offset-2 dark:focus-visible:ring-green-700 dark:focus-visible:ring-offset-gray-900 rounded-sm">{{ Str::limit($ride->id, 10) }}</a>
            <div class="text-xs text-gray-400 mt-0.5">{{ $ride->passenger_name ?? '—' }}</div>
          </td>
          <td class="px-3 py-3 flex items-center gap-3">
            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden flex items-center justify-center text-sm">{{ optional($ride->driver)->initials ?? (optional($ride->driver)->name ? substr(optional($ride->driver)->name,0,1) : '-') }}</div>
            <div>
              <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ optional($ride->driver)->name ?? ($ride->driver_name ?? '—') }}</div>
              <div class="text-xs text-gray-400">{{ optional($ride->driver)->phone ?? '' }}</div>
            </div>
          </td>
          <td class="px-3 py-3">
            <div class="text-sm text-gray-700 dark:text-gray-200">{{ $ride->pickup_checkpoint ?? '—' }} → {{ $ride->dropoff_checkpoint ?? '—' }}</div>
          </td>
          <td class="px-3 py-3">
            @php $status = strtolower($ride->status ?? 'scheduled'); @endphp
            <x-filament.badge :type="$status" :label="ucfirst(str_replace('_',' ',$status))" />
          </td>
          <td class="px-3 py-3 text-xs text-gray-500">{{ optional($ride->created_at)->diffForHumans() ?? '—' }}</td>
          <td class="px-3 py-3">
            <div class="relative inline-block text-left">
              <button class="px-2 py-1 rounded-md border border-green-200 dark:border-green-800 text-gray-800 dark:text-gray-200 transition-all duration-200 hover:bg-green-50 dark:hover:bg-green-900/30 hover:border-green-300 dark:hover:border-green-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-300 focus-visible:ring-offset-2 dark:focus-visible:ring-green-700 dark:focus-visible:ring-offset-gray-900">•••</button>
            </div>
          </td>
        </tr>
        @empty
          <tr><td colspan="6" class="p-6 text-center text-sm text-gray-500">No recent rides yet.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
