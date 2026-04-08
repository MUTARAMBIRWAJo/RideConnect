<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-purple-600 to-pink-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-purple-100">Fleet Operations</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Driver Management</h1>
            <p class="mt-2 max-w-2xl text-sm text-purple-100 sm:text-base">
                Manage driver fleet, approve/suspend licenses, monitor status and performance ratings.
            </p>
        </section>

        <!-- Fleet Stats -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <x-dashboard-card title="Total Drivers" :value="number_format($totalDrivers)" subtitle="In system" tone="purple" />
            <x-dashboard-card title="Online Now" :value="number_format($onlineDrivers)" subtitle="Active on platform" tone="green" />
            <x-dashboard-card title="Offline" :value="number_format($offlineDrivers)" subtitle="Not available" tone="slate" />
        </section>

        <!-- Drivers Table -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900">Driver Fleet</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-500 font-semibold">
                            <th class="py-3 pr-3">Name</th>
                            <th class="py-3 pr-3">Status</th>
                            <th class="py-3 pr-3">Online</th>
                            <th class="py-3 pr-3">Rating</th>
                            <th class="py-3 pr-3">Rides</th>
                            <th class="py-3 pr-3">Vehicle</th>
                            <th class="py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($drivers as $driver)
                            @php
                                $isOnline = (bool) ($driver['is_online'] ?? false);
                            @endphp
                            <tr class="border-b border-slate-100 hover:bg-slate-50 transition">
                                <td class="py-3 pr-3 font-medium text-slate-900">{{ $driver['name'] ?? 'Unknown' }}</td>
                                <td class="py-3 pr-3">
                                    <span class="inline-block rounded-full {{ in_array($driver['status'] ?? '', ['approved', 'APPROVED']) ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }} px-2 py-1 text-xs font-medium">
                                        {{ strtoupper($driver['status'] ?? 'PENDING') }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3">
                                    <span class="inline-flex items-center {{ $isOnline ? 'text-green-600' : 'text-slate-400' }}">
                                        <span class="mr-1">●</span> {{ $isOnline ? 'Online' : 'Offline' }}
                                    </span>
                                </td>
                                <td class="py-3 pr-3 font-semibold text-amber-600">⭐ {{ $driver['rating'] ?? '0' }}</td>
                                <td class="py-3 pr-3 text-slate-700">{{ $driver['completed_rides'] ?? '0' }}</td>
                                <td class="py-3 pr-3 text-slate-600 text-xs">Vehicle #{{ $driver['vehicle_id'] ?? 'N/A' }}</td>
                                <td class="py-3">
                                    <div class="flex gap-2">
                                        @if (!in_array($driver['status'] ?? '', ['approved', 'APPROVED']))
                                            <x-filament::modal width="md">
                                                <x-slot name="trigger">
                                                    <button type="button" class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded hover:bg-green-200 transition">Approve</button>
                                                </x-slot>

                                                <div class="space-y-4">
                                                    <p class="text-sm text-slate-700">Approve this driver account for dispatch eligibility?</p>
                                                    <div class="flex justify-end">
                                                        <x-filament::button size="sm" color="success" wire:click="approveDriver({{ (int) ($driver['id'] ?? 0) }})">Confirm Approve</x-filament::button>
                                                    </div>
                                                </div>
                                            </x-filament::modal>
                                        @endif
                                        <x-filament::modal width="md">
                                            <x-slot name="trigger">
                                                <button type="button" class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded hover:bg-red-200 transition">Suspend</button>
                                            </x-slot>

                                            <div class="space-y-4">
                                                <p class="text-sm text-slate-700">Suspend this driver account?</p>
                                                <div class="flex justify-end">
                                                    <x-filament::button size="sm" color="danger" wire:click="suspendDriver({{ (int) ($driver['id'] ?? 0) }})">Confirm Suspend</x-filament::button>
                                                </div>
                                            </div>
                                        </x-filament::modal>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-slate-500 text-sm">No drivers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Info Box -->
        <section class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <p class="text-xs text-blue-800">
                ℹ️ <strong>Driver Management:</strong> Approve new drivers before they can accept rides.
                Suspend drivers for compliance violations or poor ratings. Monitor online status to predict demand.
            </p>
        </section>
    </div>
</x-filament-panels::page>
