@php
    use App\Domain\Ride\RidePolicy;
@endphp

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
            <a href="{{ route('filament.admin.resources.rides.index') }}" class="text-sm font-semibold text-gray-800 dark:text-gray-200 transition-all duration-200 hover:underline hover:text-green-700 dark:hover:text-green-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-300 focus-visible:ring-offset-2 dark:focus-visible:ring-green-700 dark:focus-visible:ring-offset-gray-900 rounded-sm">{{ Str::limit($ride->id, 10) }}</a>
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
            <div class="mt-1 flex flex-wrap items-center gap-1">
              <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">{{ strtoupper((string) $ride->transport_type) }}</span>
              <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200">{{ strtoupper((string) $ride->travel_mode) }}</span>
              <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold {{ RidePolicy::canBook($ride) ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200' : (RidePolicy::canRequestTrip($ride) ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200') }}">
                {{ ucfirst(strtolower(str_replace('_', ' ', RidePolicy::getAllowedFlow($ride)))) }}
              </span>
            </div>
          </td>
          <td class="px-3 py-3">
            @php $status = strtolower($ride->status ?? 'scheduled'); @endphp
            <x-filament.badge :type="$status" :label="ucfirst(str_replace('_',' ',$status))" />
          </td>
          <td class="px-3 py-3 text-xs text-gray-500">{{ optional($ride->created_at)->diffForHumans() ?? '—' }}</td>
          <td class="px-3 py-3">
            <div class="flex flex-wrap items-center gap-2">
              @if (RidePolicy::canBook($ride))
                <a href="#" class="px-3 py-1 rounded-md bg-blue-600 text-white text-xs">Book Ride</a>
              @elseif (RidePolicy::canRequestTrip($ride))
                <a href="#" class="px-3 py-1 rounded-md bg-green-600 text-white text-xs">Request Trip</a>
              @else
                <span class="px-3 py-1 rounded-md bg-slate-100 text-slate-600 text-xs dark:bg-slate-800 dark:text-slate-300">Action Not Allowed</span>
              @endif

              <div class="relative inline-block text-left">
                <button class="px-2 py-1 rounded-md border border-green-200 dark:border-green-800 text-gray-800 dark:text-gray-200 transition-all duration-200 hover:bg-green-50 dark:hover:bg-green-900/30 hover:border-green-300 dark:hover:border-green-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-300 focus-visible:ring-offset-2 dark:focus-visible:ring-green-700 dark:focus-visible:ring-offset-gray-900">•••</button>
              </div>
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
