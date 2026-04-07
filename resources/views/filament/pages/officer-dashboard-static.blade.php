<div class="space-y-6">
    <section class="rounded-xl border-0 bg-gradient-to-r from-amber-600 to-orange-600 p-6 text-white shadow-lg">
        <p class="text-xs font-semibold uppercase tracking-wider text-orange-100">RideConnect Operations</p>
        <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Officer Dashboard</h1>
        <p class="mt-2 max-w-2xl text-sm text-orange-100 sm:text-base">
            Static Filament page with query-driven operational metrics and incident queue tables.
        </p>
    </section>

    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-dashboard-card title="Active Rides" :value="number_format($activeRidesCount)" subtitle="In progress now" tone="emerald" />
        <x-dashboard-card title="Pending Bookings" :value="number_format($pendingBookingsCount)" subtitle="Awaiting assignment" tone="amber" />
        <x-dashboard-card title="Open Tickets" :value="number_format($openTicketsCount)" subtitle="Support backlog" tone="red" />
        <x-dashboard-card title="Drivers Online" :value="number_format($onlineDriversCount)" subtitle="Available pool" tone="blue" />
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-base font-semibold text-slate-900">Operations Tools</h2>
        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @if (auth()->user()->can('view rides') || auth()->user()->can('manage rides'))
                <a href="{{ route('filament.admin.resources.bookings.index') }}" class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">Booking Queue</a>
                <a href="{{ route('filament.admin.resources.trips.index') }}" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">Trip Requests</a>
                <a href="{{ route('filament.admin.resources.drivers.index') }}" class="rounded-xl border border-purple-200 bg-purple-50 p-4 text-sm text-purple-700">Driver Pool</a>
            @endif
            @if (auth()->user()->can('manage tickets'))
                <a href="{{ route('filament.admin.resources.support-tickets.index') }}" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">Support Tickets</a>
            @endif
        </div>
    </section>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Recent Bookings</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="py-2 pr-3">ID</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentBookings as $booking)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-3 font-medium text-slate-900">#{{ $booking['id'] ?? '-' }}</td>
                                <td class="py-2 pr-3 text-slate-700">{{ strtoupper((string) ($booking['status'] ?? '-')) }}</td>
                                <td class="py-2 text-slate-600">{{ $booking['created_at'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="py-4 text-center text-slate-500">No bookings found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="text-base font-semibold text-slate-900">Recent Support Tickets</h3>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="py-2 pr-3">ID</th>
                            <th class="py-2 pr-3">Status</th>
                            <th class="py-2 pr-3">Priority</th>
                            <th class="py-2">Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentTickets as $ticket)
                            <tr class="border-b border-slate-100">
                                <td class="py-2 pr-3 font-medium text-slate-900">#{{ $ticket['id'] ?? '-' }}</td>
                                <td class="py-2 pr-3 text-slate-700">{{ strtoupper((string) ($ticket['status'] ?? '-')) }}</td>
                                <td class="py-2 pr-3 text-slate-700">{{ strtoupper((string) ($ticket['priority'] ?? 'N/A')) }}</td>
                                <td class="py-2 text-slate-600">{{ $ticket['created_at'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-slate-500">No tickets found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
