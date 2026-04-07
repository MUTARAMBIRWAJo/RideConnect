<x-filament::section>
    <x-slot name="heading">Matching Queue</x-slot>
    <x-slot name="description">Prioritize unassigned bookings and pending trip requests for faster passenger-driver matching.</x-slot>

    <div class="mb-4 flex flex-wrap items-center gap-3 text-xs">
        <span class="rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
            Active Drivers: {{ number_format($activeDriverCount) }}
        </span>
        <span class="rounded-full bg-amber-50 px-3 py-1 font-semibold text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
            Pending Bookings: {{ number_format($pendingBookings->count()) }}
        </span>
        <span class="rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
            Pending Trips: {{ number_format($pendingTrips->count()) }}
        </span>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="overflow-hidden rounded-xl border border-slate-200/70 dark:border-slate-700">
            <div class="border-b border-slate-200/70 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                Booking Queue
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-white text-xs uppercase text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Booking</th>
                            <th class="px-3 py-2 text-left">Passenger</th>
                            <th class="px-3 py-2 text-left">Route</th>
                            <th class="px-3 py-2 text-left">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($pendingBookings as $booking)
                            <tr class="bg-white dark:bg-slate-900/60">
                                <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-100">#{{ $booking->id }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $booking->user?->name ?? 'Unknown' }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">
                                    {{ \Illuminate\Support\Str::limit($booking->ride?->origin_address ?? 'N/A', 16) }}
                                    ->
                                    {{ \Illuminate\Support\Str::limit($booking->ride?->destination_address ?? 'N/A', 16) }}
                                </td>
                                <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ optional($booking->created_at)->diffForHumans() ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">No booking matching backlog.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/70 dark:border-slate-700">
            <div class="border-b border-slate-200/70 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                Trip Request Queue
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-white text-xs uppercase text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Trip</th>
                            <th class="px-3 py-2 text-left">Passenger</th>
                            <th class="px-3 py-2 text-left">Pickup</th>
                            <th class="px-3 py-2 text-left">Requested</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($pendingTrips as $trip)
                            <tr class="bg-white dark:bg-slate-900/60">
                                <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-100">#{{ $trip->id }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $trip->passenger?->full_name ?? 'Unknown' }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($trip->pickup_location ?? 'N/A', 24) }}</td>
                                <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ optional($trip->requested_at ?? $trip->created_at)->diffForHumans() ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">No pending trip requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament::section>
