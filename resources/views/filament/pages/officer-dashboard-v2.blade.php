<x-filament-panels::page>
    <div class="space-y-6" wire:poll.30s>
        <x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-amber-600 to-orange-600 text-white shadow-lg">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-orange-100">RideConnect Operations</p>
                    <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Officer Dashboard</h1>
                    <p class="mt-2 max-w-2xl text-sm text-orange-100 sm:text-base">
                        Manage rides, support tickets, and passenger-driver matching from your operational hub.
                    </p>
                </div>

                <div class="inline-flex items-center rounded-lg bg-white/15 px-3 py-2 text-xs font-medium text-white ring-1 ring-white/30 backdrop-blur">
                    Real-time matching and support queue updates.
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Quick Actions</x-slot>
            <x-slot name="description">Fast access to key operations for your role.</x-slot>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('filament.admin.pages.officer-create-booking-trip') }}" class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-700 shadow-sm transition hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 dark:hover:bg-indigo-800">
                    <p class="font-semibold">Create Booking/Trip</p>
                    <p class="mt-1 text-indigo-600 dark:text-indigo-300">Create transportation for passengers.</p>
                </a>

                @if (auth()->user()->can('view rides') || auth()->user()->can('manage rides'))
                    <a href="{{ route('filament.admin.resources.bookings.index') }}" class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-700 shadow-sm transition hover:bg-blue-100 dark:border-blue-800 dark:bg-blue-900 dark:text-blue-200 dark:hover:bg-blue-800">
                        <p class="font-semibold">Booking Queue</p>
                        <p class="mt-1 text-blue-600 dark:text-blue-300">Manage pending bookings and assignments.</p>
                    </a>
                    <a href="{{ route('filament.admin.resources.trips.index') }}" class="rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700 shadow-sm transition hover:bg-green-100 dark:border-green-800 dark:bg-green-900 dark:text-green-200 dark:hover:bg-green-800">
                        <p class="font-semibold">Trip Requests</p>
                        <p class="mt-1 text-green-600 dark:text-green-300">Monitor active and pending trips.</p>
                    </a>
                @endif

                @if (auth()->user()->can('view rides') || auth()->user()->can('manage rides'))
                    <a href="{{ route('filament.admin.resources.drivers.index') }}" class="rounded-xl border border-purple-200 bg-purple-50 p-4 text-sm text-purple-700 shadow-sm transition hover:bg-purple-100 dark:border-purple-800 dark:bg-purple-900 dark:text-purple-200 dark:hover:bg-purple-800">
                        <p class="font-semibold">Driver Pool</p>
                        <p class="mt-1 text-purple-600 dark:text-purple-300">View available drivers and status.</p>
                    </a>
                @endif

                @if (auth()->user()->can('manage tickets'))
                    <a href="{{ route('filament.admin.resources.support-tickets.index') }}" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 shadow-sm transition hover:bg-red-100 dark:border-red-800 dark:bg-red-900 dark:text-red-200 dark:hover:bg-red-800">
                        <p class="font-semibold">Support Tickets</p>
                        <p class="mt-1 text-red-600 dark:text-red-300">Handle customer complaints and issues.</p>
                    </a>
                @endif
            </div>
        </x-filament::section>

        <x-filament-widgets::widgets
            :columns="$this->getColumns()"
            :widgets="$this->getWidgets()"
            :data="$this->getWidgetData()"
        />
    </div>
</x-filament-panels::page>
