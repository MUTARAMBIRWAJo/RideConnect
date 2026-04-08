<x-filament-panels::page>
    <div class="space-y-6" wire:poll.20s>
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-green-600 to-emerald-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-green-100">RideConnect Operations</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Officer Dashboard</h1>
            <p class="mt-2 max-w-2xl text-sm text-green-100 sm:text-base">
                Real-time operations monitoring and incident management for ride-hailing platform.
            </p>
        </section>

        <!-- Primary KPI Cards -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <x-dashboard-card title="Active Rides" :value="number_format($activeRidesCount)" subtitle="Currently in progress" tone="emerald" />
            <x-dashboard-card title="Pending Bookings" :value="number_format($pendingBookingsCount)" subtitle="Awaiting assignment" tone="amber" />
            <x-dashboard-card title="Open Tickets" :value="number_format($openTicketsCount)" subtitle="Support backlog" tone="red" />
            <x-dashboard-card title="Drivers Online" :value="number_format($onlineDriversCount)" subtitle="Available pool" tone="blue" />
        </section>

        <!-- Secondary KPI Cards -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <x-dashboard-card title="Overdue Bookings" :value="number_format($overdueBookingsCount)" subtitle="Pending 15+ minutes" tone="amber" />
            <x-dashboard-card title="Priority Tickets" :value="number_format($highPriorityTicketsCount)" subtitle="High/Urgent issues" tone="red" />
            <x-dashboard-card title="Cancelled Today" :value="number_format($cancelledRidesTodayCount)" subtitle="Requires follow-up" tone="purple" />
        </section>

        <!-- Quick Actions -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Quick Actions</h2>
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <a href="{{ \App\Filament\Pages\Officer\LiveRidesPage::getUrl(panel: 'officer') }}" class="rounded-xl border border-blue-200 bg-blue-50 p-3 text-center text-sm font-medium text-blue-700 hover:bg-blue-100 transition">
                    📍 Live Rides
                </a>
                <a href="{{ \App\Filament\Pages\Officer\DriverManagementPage::getUrl(panel: 'officer') }}" class="rounded-xl border border-purple-200 bg-purple-50 p-3 text-center text-sm font-medium text-purple-700 hover:bg-purple-100 transition">
                    🚗 Drivers
                </a>
                <a href="{{ \App\Filament\Pages\Officer\ComplaintsPage::getUrl(panel: 'officer') }}" class="rounded-xl border border-red-200 bg-red-50 p-3 text-center text-sm font-medium text-red-700 hover:bg-red-100 transition">
                    🔔 Complaints
                </a>
                <a href="{{ \App\Filament\Pages\Officer\AIInsightsPage::getUrl(panel: 'officer') }}" class="rounded-xl border border-indigo-200 bg-indigo-50 p-3 text-center text-sm font-medium text-indigo-700 hover:bg-indigo-100 transition">
                    💡 Insights
                </a>
                <a href="{{ \App\Filament\Pages\Officer\ComplaintsPage::getUrl(panel: 'officer') }}?filter=urgent" class="rounded-xl border border-orange-200 bg-orange-50 p-3 text-center text-sm font-medium text-orange-700 hover:bg-orange-100 transition">
                    ⚡ Escalations
                </a>
            </div>
        </section>

        <!-- Recent Bookings & Tickets -->
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
                                    <td class="py-2 pr-3">
                                        <span class="inline-block rounded-full bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700">
                                            {{ strtoupper($booking['status'] ?? '-') }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-slate-600 text-xs">{{ \Carbon\Carbon::parse($booking['created_at'])->format('M d, H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-slate-500 text-sm">No bookings found.</td>
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
                                    <td class="py-2 pr-3">
                                        <span class="inline-block rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-700">
                                            {{ strtoupper($ticket['status'] ?? '-') }}
                                        </span>
                                    </td>
                                    <td class="py-2 pr-3 text-slate-700">{{ strtoupper($ticket['priority'] ?? 'N/A') }}</td>
                                    <td class="py-2 text-slate-600 text-xs">{{ \Carbon\Carbon::parse($ticket['created_at'])->format('M d, H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-slate-500 text-sm">No tickets found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Escalation Queue & Unassigned Rides -->
        <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
            <div class="rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
                <h3 class="text-base font-semibold text-red-900">⚡ Escalation Queue</h3>
                <p class="mt-1 text-xs text-red-700">Urgent tickets requiring immediate attention.</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-red-200 text-left text-xs uppercase tracking-wide text-red-700">
                                <th class="py-2 pr-3">ID</th>
                                <th class="py-2 pr-3">Priority</th>
                                <th class="py-2">Timer</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($escalationTickets as $ticket)
                                <tr class="border-b border-red-100">
                                    <td class="py-2 pr-3 font-medium text-red-900">#{{ $ticket['id'] ?? '-' }}</td>
                                    <td class="py-2 pr-3">
                                        <span class="inline-block rounded-full bg-red-200 px-2 py-1 text-xs font-medium text-red-900">
                                            {{ strtoupper($ticket['priority'] ?? 'NORMAL') }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-red-700 text-xs">{{ \Carbon\Carbon::parse($ticket['created_at'])->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-red-600 text-sm">✓ All clear</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-xl border border-blue-200 bg-blue-50 p-5 shadow-sm">
                <h3 class="text-base font-semibold text-blue-900">📍 Unassigned Rides Queue</h3>
                <p class="mt-1 text-xs text-blue-700">Rides waiting for driver assignment.</p>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-blue-200 text-left text-xs uppercase tracking-wide text-blue-700">
                                <th class="py-2 pr-3">ID</th>
                                <th class="py-2 pr-3">Route</th>
                                <th class="py-2">Wait</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($unassignedRides as $ride)
                                <tr class="border-b border-blue-100">
                                    <td class="py-2 pr-3 font-medium text-blue-900">#{{ $ride['id'] ?? '-' }}</td>
                                    <td class="py-2 pr-3 text-xs text-blue-700">{{ substr($ride['origin_address'] ?? 'Unknown', 0, 20) }}...</td>
                                    <td class="py-2 text-blue-700 text-xs">{{ \Carbon\Carbon::parse($ride['created_at'])->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="py-4 text-center text-blue-600 text-sm">✓ All assigned</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
