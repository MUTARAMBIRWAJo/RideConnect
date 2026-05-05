<x-filament-panels::page>
	<div class="space-y-6" wire:poll.60s>
		<x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-slate-700 via-gray-700 to-zinc-700 text-white shadow-xl">
			<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
				<div>
					<p class="text-xs font-semibold uppercase tracking-wider text-slate-200 flex items-center gap-2">
						<x-heroicon-o-shield-check class="w-4 h-4" />
						RideConnect Control Center
					</p>
					<h1 class="mt-1 text-2xl font-bold sm:text-3xl">Super Admin Dashboard</h1>
					<p class="mt-2 max-w-2xl text-sm text-slate-200 sm:text-base">
						Monitor platform health, ride activity, and revenue from a resilient, cached dashboard designed for production.
					</p>
				</div>

				<div class="inline-flex items-center rounded-lg bg-white/20 px-4 py-2 text-sm font-medium text-white ring-1 ring-white/30 backdrop-blur-sm">
					<x-heroicon-o-arrow-path class="w-4 h-4 mr-2" />
					Live metrics are cache-backed and auto-refresh in safe intervals
				</div>
			</div>
		</x-filament::section>

		<x-filament::section>
			<x-slot name="heading">Operations Snapshot</x-slot>
			<x-slot name="description">Real-time queue, approval, and payment pressure indicators for immediate decisions.</x-slot>

			<div class="grid gap-4 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-4">
				<div class="group rounded-xl border border-amber-200 bg-gradient-to-br from-amber-50 to-yellow-50 p-4 sm:p-5 text-sm text-amber-900 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-amber-800 dark:bg-gradient-to-br dark:from-amber-900 dark:to-yellow-900 dark:text-amber-100">
					<div class="flex items-center justify-between mb-3">
						<p class="text-xs uppercase tracking-wide text-amber-700 dark:text-amber-300 font-semibold">Pending Drivers</p>
						<div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-800">
							<x-heroicon-o-user-group class="w-4 h-4 text-amber-600 dark:text-amber-400" />
						</div>
					</div>
					<p class="text-3xl font-bold">{{ number_format($operationsSnapshot['pending_drivers'] ?? 0) }}</p>
					<p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Awaiting onboarding approval.</p>
				</div>

				<div class="group rounded-xl border border-blue-200 bg-gradient-to-br from-blue-50 to-indigo-50 p-4 sm:p-5 text-sm text-blue-900 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-blue-800 dark:bg-gradient-to-br dark:from-blue-900 dark:to-indigo-900 dark:text-blue-100">
					<div class="flex items-center justify-between mb-3">
						<p class="text-xs uppercase tracking-wide text-blue-700 dark:text-blue-300 font-semibold">Pending Users</p>
						<div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-800">
							<x-heroicon-o-users class="w-4 h-4 text-blue-600 dark:text-blue-400" />
						</div>
					</div>
					<p class="text-3xl font-bold">{{ number_format($operationsSnapshot['pending_users'] ?? 0) }}</p>
					<p class="mt-2 text-xs text-blue-700 dark:text-blue-300">Accounts blocked by approval gate.</p>
				</div>

				<div class="group rounded-xl border border-red-200 bg-gradient-to-br from-red-50 to-pink-50 p-4 sm:p-5 text-sm text-red-900 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-red-800 dark:bg-gradient-to-br dark:from-red-900 dark:to-pink-900 dark:text-red-100">
					<div class="flex items-center justify-between mb-3">
						<p class="text-xs uppercase tracking-wide text-red-700 dark:text-red-300 font-semibold">Failed Payments (24h)</p>
						<div class="p-2 rounded-lg bg-red-100 dark:bg-red-800">
							<x-heroicon-o-exclamation-triangle class="w-4 h-4 text-red-600 dark:text-red-400" />
						</div>
					</div>
					<p class="text-3xl font-bold">{{ number_format($operationsSnapshot['failed_payments_24h'] ?? 0) }}</p>
					<p class="mt-2 text-xs text-red-700 dark:text-red-300">Needs retry, alerting, or follow-up.</p>
				</div>

				<div class="group rounded-xl border border-purple-200 bg-gradient-to-br from-purple-50 to-violet-50 p-4 sm:p-5 text-sm text-purple-900 shadow-sm transition-all duration-200 hover:shadow-lg hover:scale-105 dark:border-purple-800 dark:bg-gradient-to-br dark:from-purple-900 dark:to-violet-900 dark:text-purple-100">
					<div class="flex items-center justify-between mb-3">
						<p class="text-xs uppercase tracking-wide text-purple-700 dark:text-purple-300 font-semibold">Event Outbox Pending</p>
						<div class="p-2 rounded-lg bg-purple-100 dark:bg-purple-800">
							<x-heroicon-o-inbox class="w-4 h-4 text-purple-600 dark:text-purple-400" />
						</div>
					</div>
					<p class="text-3xl font-bold">{{ number_format($operationsSnapshot['pending_outbox'] ?? 0) }}</p>
					<p class="mt-2 text-xs text-purple-700 dark:text-purple-300">Domain events waiting publication.</p>
				</div>
			</div>
		</x-filament::section>

		<x-filament::section>
			<x-slot name="heading">Super Admin Actions</x-slot>
			<x-slot name="description">High-impact controls to reduce response time during operations.</x-slot>

			<div class="grid gap-3 sm:grid-cols-1 md:grid-cols-2 xl:grid-cols-4">
				<x-filament::modal width="md">
					<x-slot name="trigger">
						<button type="button" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-medium text-slate-800 shadow-sm transition hover:bg-slate-50">
							Clear App Caches
							<p class="mt-1 text-xs font-normal text-slate-500">Runs optimize:clear to refresh runtime caches.</p>
						</button>
					</x-slot>

					<div class="space-y-4">
						<p class="text-sm text-slate-700">This will clear config, route, view, and Filament caches. Continue?</p>
						<div class="flex justify-end">
							<x-filament::button color="warning" size="sm" wire:click="clearApplicationCaches">Confirm Clear</x-filament::button>
						</div>
					</div>
				</x-filament::modal>

				<x-filament::modal width="md">
					<x-slot name="trigger">
						<button type="button" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-medium text-slate-800 shadow-sm transition hover:bg-slate-50">
							Restart Queue Workers
							<p class="mt-1 text-xs font-normal text-slate-500">Signals workers to restart safely.</p>
						</button>
					</x-slot>

					<div class="space-y-4">
						<p class="text-sm text-slate-700">Queue workers will restart after current jobs finish. Continue?</p>
						<div class="flex justify-end">
							<x-filament::button color="success" size="sm" wire:click="restartQueueWorkers">Confirm Restart</x-filament::button>
						</div>
					</div>
				</x-filament::modal>

				<x-filament::modal width="md">
					<x-slot name="trigger">
						<button type="button" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-medium text-slate-800 shadow-sm transition hover:bg-slate-50">
							Approve Pending Drivers
							<p class="mt-1 text-xs font-normal text-slate-500">Bulk approves all drivers with pending status.</p>
						</button>
					</x-slot>

					<div class="space-y-4">
						<p class="text-sm text-slate-700">
							<strong>{{ $driverApprovalPreview ?? 0 }} driver account(s)</strong> will be approved in one operation.
						</p>
						
						<div>
							<label class="block text-xs font-medium uppercase text-slate-700 mb-2">Approval Reason (required)</label>
							<textarea 
								wire:model.live="driverApprovalReason" 
								class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
								rows="3"
								placeholder="Enter the reason for bulk driver approval (e.g., 'Onboarding volume catchup', 'Compliance review completed')..."
								@focus="$wire.previewPendingDriverApprovals"
							></textarea>
						</div>

						<div class="flex justify-end gap-2">
							<x-filament::button color="primary" size="sm" wire:click="approveAllPendingDrivers">Confirm Approve</x-filament::button>
						</div>
					</div>
				</x-filament::modal>

				<x-filament::modal width="md">
					<x-slot name="trigger">
						<button type="button" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-medium text-slate-800 shadow-sm transition hover:bg-slate-50">
							Approve Pending Users
							<p class="mt-1 text-xs font-normal text-slate-500">Bulk approves all unapproved user accounts.</p>
						</button>
					</x-slot>

					<div class="space-y-4">
						<p class="text-sm text-slate-700">
							<strong>{{ $userApprovalPreview ?? 0 }} user account(s)</strong> will be approved now.
						</p>
						
						<div>
							<label class="block text-xs font-medium uppercase text-slate-700 mb-2">Approval Reason (required)</label>
							<textarea 
								wire:model.live="userApprovalReason" 
								class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 placeholder-slate-400 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
								rows="3"
								placeholder="Enter the reason for bulk user approval (e.g., 'Backend staff onboarding', 'Admin team expansion')..."
								@focus="$wire.previewPendingUserApprovals"
							></textarea>
						</div>

						<div class="flex justify-end gap-2">
							<x-filament::button color="primary" size="sm" wire:click="approveAllPendingUsers">Confirm Approve</x-filament::button>
						</div>
					</div>
				</x-filament::modal>
			</div>

			<div class="mt-4 flex flex-wrap items-center gap-3 text-xs text-slate-500">
				<x-filament::button color="gray" size="xs" wire:click="refreshOperationsSnapshot">Refresh Snapshot</x-filament::button>
				<x-filament::button color="gray" size="xs" wire:click="downloadSuperAdminActionLogs">
					Export Action Logs (CSV)
				</x-filament::button>
				@if ($lastMaintenanceActionAt)
					<span>Last maintenance action: {{ $lastMaintenanceActionAt }}</span>
				@endif
			</div>
		</x-filament::section>
	</div>
</x-filament-panels::page>