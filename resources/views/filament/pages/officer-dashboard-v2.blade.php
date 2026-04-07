<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-lg">
            <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-100">RideConnect Operations</p>
                    <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Officer Dashboard</h1>
                    <p class="mt-2 max-w-2xl text-sm text-emerald-100 sm:text-base">
                        Monitor compliance, incident flags, and operational health for officer workflows.
                    </p>
                </div>

                <div class="inline-flex items-center rounded-lg bg-white/15 px-3 py-2 text-xs font-medium text-white ring-1 ring-white/30 backdrop-blur">
                    Access is role-scoped and panel-protected.
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Officer Quick Actions</x-slot>
            <x-slot name="description">Use these shortcuts to resolve support cases, dispatch drivers faster, and track passenger service quality.</x-slot>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @if (\App\Filament\Resources\BookingResource::canViewAny())
                    <a href="{{ \App\Filament\Resources\BookingResource::getUrl('index') }}" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm shadow-sm transition hover:bg-emerald-100 dark:border-emerald-900/60 dark:bg-emerald-900/20 dark:hover:bg-emerald-900/35">
                        <p class="font-semibold text-emerald-800 dark:text-emerald-200">Booking Queue</p>
                        <p class="mt-1 text-emerald-700/80 dark:text-emerald-300/90">Handle pending and confirmed bookings needing assignment.</p>
                    </a>
                @endif

                @if (\App\Filament\Resources\TripResource::canViewAny())
                    <a href="{{ \App\Filament\Resources\TripResource::getUrl('index') }}" class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm shadow-sm transition hover:bg-blue-100 dark:border-blue-900/60 dark:bg-blue-900/20 dark:hover:bg-blue-900/35">
                        <p class="font-semibold text-blue-800 dark:text-blue-200">Trip Requests</p>
                        <p class="mt-1 text-blue-700/80 dark:text-blue-300/90">Review pending trip requests and move them to active service.</p>
                    </a>
                @endif

                @if (\App\Filament\Resources\DriverResource::canViewAny())
                    <a href="{{ \App\Filament\Resources\DriverResource::getUrl('index') }}" class="rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm shadow-sm transition hover:bg-indigo-100 dark:border-indigo-900/60 dark:bg-indigo-900/20 dark:hover:bg-indigo-900/35">
                        <p class="font-semibold text-indigo-800 dark:text-indigo-200">Driver Pool</p>
                        <p class="mt-1 text-indigo-700/80 dark:text-indigo-300/90">Find approved and available drivers for fast dispatching.</p>
                    </a>
                @endif

                @if (\App\Filament\Resources\TicketResource::canViewAny())
                    <a href="{{ \App\Filament\Resources\TicketResource::getUrl('index') }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm shadow-sm transition hover:bg-amber-100 dark:border-amber-900/60 dark:bg-amber-900/20 dark:hover:bg-amber-900/35">
                        <p class="font-semibold text-amber-800 dark:text-amber-200">Support Tickets</p>
                        <p class="mt-1 text-amber-700/80 dark:text-amber-300/90">Resolve complaints and keep passengers and drivers informed.</p>
                    </a>
                @endif
            </div>
        </x-filament::section>

        <x-filament-widgets::widgets
            :columns="$this->getColumns()"
            :data="[
                ...(property_exists($this, 'filters') ? ['filters' => $this->filters] : []),
                ...$this->getWidgetData(),
            ]"
            :widgets="$this->getVisibleWidgets()"
        />
    </div>
</x-filament-panels::page>
