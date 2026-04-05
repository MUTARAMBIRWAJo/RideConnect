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
            <x-slot name="heading">Officer Notes</x-slot>
            <x-slot name="description">This page intentionally uses a single-root structure for Livewire stability.</x-slot>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    <p class="font-semibold">Permissions</p>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">Dashboard widgets and actions remain governed by your existing role and policy checks.</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
                    <p class="font-semibold">Routing</p>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">Role-based redirects still route officers to this page via the same dashboard mapping.</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 sm:col-span-2 xl:col-span-1">
                    <p class="font-semibold">Reliability</p>
                    <p class="mt-1 text-gray-500 dark:text-gray-400">Single-root markup prevents Livewire multiple-root exceptions in production and local runs.</p>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
