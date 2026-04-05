<x-filament-panels::page>
	<div class="space-y-6">
		<x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-teal-600 to-cyan-600 text-white shadow-lg">
			<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
				<div>
					<p class="text-xs font-semibold uppercase tracking-wider text-cyan-100">RideConnect Control Center</p>
					<h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Super Admin Dashboard</h1>
					<p class="mt-2 max-w-2xl text-sm text-cyan-100 sm:text-base">
						Monitor platform health, ride activity, and revenue from a resilient, cached dashboard designed for production.
					</p>
				</div>

				<div class="inline-flex items-center rounded-lg bg-white/15 px-3 py-2 text-xs font-medium text-white ring-1 ring-white/30 backdrop-blur">
					Live metrics are cache-backed and auto-refresh in safe intervals.
				</div>
			</div>
		</x-filament::section>

		<x-filament::section>
			<x-slot name="heading">Operational Notes</x-slot>
			<x-slot name="description">Designed to stay available under partial failures and high query load.</x-slot>

			<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
				<div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
					<p class="font-semibold">Performance</p>
					<p class="mt-1 text-gray-500 dark:text-gray-400">Metrics are cached for short windows to reduce database pressure.</p>
				</div>

				<div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200">
					<p class="font-semibold">Resilience</p>
					<p class="mt-1 text-gray-500 dark:text-gray-400">Risky data access paths are guarded so the UI remains responsive.</p>
				</div>

				<div class="rounded-xl border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 sm:col-span-2 xl:col-span-1">
					<p class="font-semibold">Fallback Mode</p>
					<p class="mt-1 text-gray-500 dark:text-gray-400">Set DASHBOARD_SUPER_STATIC_MODE=true to temporarily disable dynamic widgets.</p>
				</div>
			</div>
		</x-filament::section>
	</div>
</x-filament-panels::page>