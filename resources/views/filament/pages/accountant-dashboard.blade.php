<x-filament-panels::page>
	<div class="space-y-6">
		<x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-lg">
			<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
				<div>
					<p class="text-xs font-semibold uppercase tracking-wider text-teal-100">RideConnect Finance Center</p>
					<h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Accountant Dashboard</h1>
					<p class="mt-2 max-w-2xl text-sm text-teal-100 sm:text-base">
						Track revenue, payments, and financial metrics with comprehensive reporting and analytics.
					</p>
				</div>

				<div class="inline-flex items-center rounded-lg bg-white/15 px-3 py-2 text-xs font-medium text-white ring-1 ring-white/30 backdrop-blur">
					Financial data is cached and updated in real-time.
				</div>
			</div>
		</x-filament::section>

		<x-filament::section>
			<x-slot name="heading">Financial Operations</x-slot>
			<x-slot name="description">Key financial management tools and resources.</x-slot>

			<div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
				@if (auth()->user()->can('view finances'))
					<a href="{{ route('filament.admin.resources.payments.index') }}" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 shadow-sm transition hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900 dark:text-emerald-200 dark:hover:bg-emerald-800">
						<p class="font-semibold">Payments</p>
						<p class="mt-1 text-emerald-600 dark:text-emerald-300">Review and manage all payment records.</p>
					</a>
				@endif

				@if (auth()->user()->can('view finances'))
					<a href="{{ route('filament.admin.resources.commissions.index') }}" class="rounded-xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-700 shadow-sm transition hover:bg-cyan-100 dark:border-cyan-800 dark:bg-cyan-900 dark:text-cyan-200 dark:hover:bg-cyan-800">
						<p class="font-semibold">Commissions</p>
						<p class="mt-1 text-cyan-600 dark:text-cyan-300">Track driver and partner commissions.</p>
					</a>
				@endif

				@if (auth()->user()->can('view finances'))
					<a href="{{ route('filament.admin.resources.revenue.index') }}" class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-700 shadow-sm transition hover:bg-teal-100 dark:border-teal-800 dark:bg-teal-900 dark:text-teal-200 dark:hover:bg-teal-800">
						<p class="font-semibold">Revenue</p>
						<p class="mt-1 text-teal-600 dark:text-teal-300">Monitor revenue trends and summaries.</p>
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
