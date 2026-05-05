<x-filament-panels::page>
    <div class="space-y-6" wire:poll.30s>
        <x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-orange-500 via-amber-500 to-yellow-500 text-white shadow-xl">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-orange-100 flex items-center gap-2">
                        <x-heroicon-o-cog-6-tooth class="w-4 h-4" />
                        RideConnect Operations
                    </p>
                    <h1 class="mt-1 text-2xl font-bold sm:text-3xl">Officer Dashboard</h1>
                    <p class="mt-2 max-w-2xl text-sm text-orange-100 sm:text-base">
                        Manage rides, support tickets, and passenger-driver matching from your operational hub.
                    </p>
                </div>

                <div class="inline-flex items-center rounded-lg bg-white/20 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/30 backdrop-blur-sm">
                    <x-heroicon-o-arrow-path class="w-4 h-4 mr-2" />
                    Real-time matching and support queue updates
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Quick Actions</x-slot>
            <x-slot name="description">Fast access to key operations for your role.</x-slot>

            <div class="grid gap-4 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-4">
                <a href="{{ route('filament.officer.pages.officer-create-booking-trip') }}" class="group rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-blue-50 p-4 sm:p-5 text-sm text-indigo-800 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-indigo-800 dark:bg-gradient-to-br dark:from-indigo-900 dark:to-blue-900 dark:text-indigo-200">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="p-2 rounded-lg bg-indigo-100 dark:bg-indigo-800">
                            <x-heroicon-o-plus-circle class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <p class="font-semibold">Create Booking/Trip</p>
                    </div>
                    <p class="text-indigo-700 dark:text-indigo-300">Create transportation for passengers.</p>
                </a>

                @if (auth()->user()->can('view rides') || auth()->user()->can('manage rides'))
                    <a href="{{ route('filament.admin.resources.bookings.index') }}" class="group rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-cyan-50 p-4 sm:p-5 text-sm text-blue-800 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-blue-800 dark:bg-gradient-to-br dark:from-blue-900 dark:to-cyan-900 dark:text-blue-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-800">
                                <x-heroicon-o-queue-list class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <p class="font-semibold">Booking Queue</p>
                        </div>
                        <p class="text-blue-700 dark:text-blue-300">Manage pending bookings and assignments.</p>
                    </a>
                    <a href="{{ route('filament.admin.resources.trips.index') }}" class="group rounded-xl border border-green-200 bg-gradient-to-br from-green-50 to-emerald-50 p-4 sm:p-5 text-sm text-green-800 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-green-800 dark:bg-gradient-to-br dark:from-green-900 dark:to-emerald-900 dark:text-green-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-green-100 dark:bg-green-800">
                                <x-heroicon-o-map-pin class="w-5 h-5 text-green-600 dark:text-green-400" />
                            </div>
                            <p class="font-semibold">Trip Requests</p>
                        </div>
                        <p class="text-green-700 dark:text-green-300">Monitor active and pending trips.</p>
                    </a>
                @endif

                @if (auth()->user()->can('view rides') || auth()->user()->can('manage rides'))
                    <a href="{{ route('filament.admin.resources.drivers.index') }}" class="group rounded-xl border border-purple-200 bg-gradient-to-br from-purple-50 to-violet-50 p-4 sm:p-5 text-sm text-purple-800 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-purple-800 dark:bg-gradient-to-br dark:from-purple-900 dark:to-violet-900 dark:text-purple-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-purple-100 dark:bg-purple-800">
                                <x-heroicon-o-users class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <p class="font-semibold">Driver Pool</p>
                        </div>
                        <p class="text-purple-700 dark:text-purple-300">View available drivers and status.</p>
                    </a>
                @endif

                @if (auth()->user()->can('manage tickets'))
                    <a href="{{ route('filament.admin.resources.tickets.index') }}" class="group rounded-xl border border-red-200 bg-gradient-to-br from-red-50 to-pink-50 p-4 sm:p-5 text-sm text-red-800 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-red-800 dark:bg-gradient-to-br dark:from-red-900 dark:to-pink-900 dark:text-red-200">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="p-2 rounded-lg bg-red-100 dark:bg-red-800">
                                <x-heroicon-o-chat-bubble-left-right class="w-5 h-5 text-red-600 dark:text-red-400" />
                            </div>
                            <p class="font-semibold">Support Tickets</p>
                        </div>
                        <p class="text-red-700 dark:text-red-300">Handle customer complaints and issues.</p>
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
