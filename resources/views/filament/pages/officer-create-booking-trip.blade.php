<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-lg">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-100">Officer Operations</p>
                <h1 class="text-2xl font-semibold sm:text-3xl">Create Booking or Trip</h1>
                <p class="max-w-2xl text-sm text-emerald-100 sm:text-base">
                    Use the live production forms below. Booking creates passenger reservations on scheduled rides with fare validation, while Trip creation is limited to BUS public-transport routes and can be seeded from an existing booking.
                </p>
            </div>
        </x-filament::section>

        <div class="grid gap-4 md:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Booking Form</x-slot>
                <x-slot name="description">Create bookings for passengers on scheduled rides, with route-based fare validation and pickup/dropoff location selection.</x-slot>

                <div class="space-y-3">
                    <a
                        href="{{ route('filament.officer.resources.bookings.create') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                    >
                        Open Live Booking Form
                    </a>
                    <a
                        href="{{ route('filament.officer.resources.bookings.index') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                    >
                        View Booking List
                    </a>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Trip Form</x-slot>
                <x-slot name="description">Create direct trips for BUS public transport rides or turn an existing booking into a trip. Driver and fare are auto-filled from ride or booking data.</x-slot>

                <div class="space-y-3">
                    <a
                        href="{{ route('filament.officer.resources.trips.create') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                    >
                        Open Live Trip Form
                    </a>
                    <a
                        href="{{ route('filament.officer.resources.trips.index') }}"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                    >
                        View Trip List
                    </a>
                </div>
            </x-filament::section>
        </div>

        <x-filament::section>
            <x-slot name="heading">Why This Is Live</x-slot>
            <ul class="list-disc space-y-1 pl-5 text-sm text-gray-700">
                <li>Forms are native Filament resource create pages backed by your Laravel models.</li>
                <li>Validation runs server-side before save.</li>
                <li>Map location picker is active via existing map picker component.</li>
                <li>Create buttons persist real records to your database.</li>
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
