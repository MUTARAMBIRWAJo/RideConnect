<x-filament::section>
    <x-slot name="heading">Service Cases</x-slot>
    <x-slot name="description">Track unresolved tickets and recent cancellations to quickly assist passengers and drivers.</x-slot>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="overflow-hidden rounded-xl border border-slate-200/70 dark:border-slate-700">
            <div class="border-b border-slate-200/70 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                Open Tickets
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-white text-xs uppercase text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Ticket</th>
                            <th class="px-3 py-2 text-left">Trip</th>
                            <th class="px-3 py-2 text-left">Reason</th>
                            <th class="px-3 py-2 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($openTickets as $ticket)
                            @php $state = strtolower((string) $ticket->status); @endphp
                            <tr class="bg-white dark:bg-slate-900/60">
                                <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-100">#{{ $ticket->id }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">#{{ $ticket->trip_id ?? 'N/A' }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($ticket->reason ?? 'N/A', 36) }}</td>
                                <td class="px-3 py-2">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ in_array($state, ['open', 'pending'], true) ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200' }}">
                                        {{ strtoupper((string) $ticket->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">No open ticket cases.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200/70 dark:border-slate-700">
            <div class="border-b border-slate-200/70 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
                Recent Cancelled Rides
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-white text-xs uppercase text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                        <tr>
                            <th class="px-3 py-2 text-left">Ride</th>
                            <th class="px-3 py-2 text-left">Driver</th>
                            <th class="px-3 py-2 text-left">Reason</th>
                            <th class="px-3 py-2 text-left">When</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($cancelledRides as $ride)
                            <tr class="bg-white dark:bg-slate-900/60">
                                <td class="px-3 py-2 font-medium text-slate-800 dark:text-slate-100">#{{ $ride->id }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $ride->driver?->user?->name ?? 'Unassigned' }}</td>
                                <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($ride->cancellation_reason ?? 'No reason', 32) }}</td>
                                <td class="px-3 py-2 text-slate-500 dark:text-slate-400">{{ optional($ride->cancelled_at ?? $ride->updated_at)->diffForHumans() ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-3 py-6 text-center text-slate-500 dark:text-slate-400">No recent ride cancellations.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament::section>
