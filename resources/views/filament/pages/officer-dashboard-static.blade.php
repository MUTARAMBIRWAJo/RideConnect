<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-amber-600 to-orange-600 text-white shadow-lg">
            <div class="flex flex-col gap-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-orange-100">RideConnect Operations</p>
                <h1 class="text-2xl font-semibold sm:text-3xl">Officer Dashboard</h1>
                <p class="max-w-2xl text-sm text-orange-100 sm:text-base">
                    Operational command center for dispatch, support, and incident response.
                </p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Operations Tools</x-slot>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @if (auth()->user()->can('view rides') || auth()->user()->can('manage rides'))
                    <a href="{{ route('filament.admin.resources.bookings.index') }}" class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700">Booking Queue</a>
                    <a href="{{ route('filament.admin.resources.trips.index') }}" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700">Trip Requests</a>
                    <a href="{{ route('filament.admin.resources.drivers.index') }}" class="rounded-xl border border-purple-200 bg-purple-50 p-4 text-sm text-purple-700">Driver Pool</a>
                @endif
                @if (auth()->user()->can('manage tickets'))
                    <a href="{{ route('filament.admin.resources.support-tickets.index') }}" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">Support Tickets</a>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
