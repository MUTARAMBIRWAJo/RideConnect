<div>
    <x-filament-panels::page>
        @php($userStats = $this->getUserManagementStats())
        @php($slowModeEnabled = (bool) config('dashboard.performance.slow_mode', false))
        <div class="space-y-6">
            <section class="relative overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-r from-slate-900 via-sky-900 to-cyan-900 p-6 shadow-sm dark:border-slate-800">
                <div class="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-white/10 blur-2xl"></div>
                <div class="absolute -left-14 bottom-0 h-28 w-28 rounded-full bg-cyan-300/20 blur-xl"></div>
                <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-200">Command Center</p>
                        <h2 class="mt-2 text-2xl font-semibold text-white sm:text-3xl">Superadmin Operations Cockpit</h2>
                        <p class="mt-2 text-sm text-cyan-100/90">A unified and responsive control surface for live mobility operations, financial telemetry, user governance, and AI-driven insights.</p>
                        <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                            <span class="inline-flex items-center rounded-full border border-cyan-300/40 bg-cyan-300/10 px-2.5 py-1 font-semibold text-cyan-100">Users {{ number_format($userStats['total']) }}</span>
                            <span class="inline-flex items-center rounded-full border border-amber-300/50 bg-amber-300/10 px-2.5 py-1 font-semibold text-amber-100">Pending {{ number_format($userStats['pending']) }}</span>
                            <span class="inline-flex items-center rounded-full border border-emerald-300/50 bg-emerald-300/10 px-2.5 py-1 font-semibold text-emerald-100">Managers {{ number_format($userStats['managers']) }}</span>
                            <span class="inline-flex items-center rounded-full border border-violet-300/50 bg-violet-300/10 px-2.5 py-1 font-semibold text-violet-100">Mobile {{ number_format($userStats['mobile']) }}</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            title="Uses low-bandwidth polling/lazy profile"
                            aria-label="Uses low-bandwidth polling/lazy profile"
                            class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold tracking-wide {{ $slowModeEnabled ? 'border-amber-300/60 bg-amber-300/20 text-amber-100' : 'border-emerald-300/60 bg-emerald-300/20 text-emerald-100' }}"
                        >
                            Slow Mode {{ $slowModeEnabled ? 'ON' : 'OFF' }}
                        </span>
                        @if($this->canManageUsers())
                            <a
                                href="{{ \App\Filament\Resources\UserResource::getUrl('index') }}"
                                class="inline-flex items-center rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm font-medium text-white transition hover:bg-white/20"
                            >
                                Manage Users
                            </a>
                            <a
                                href="{{ \App\Filament\Resources\UserResource::getUrl('create') }}"
                                class="inline-flex items-center rounded-lg border border-cyan-300/50 bg-cyan-300/20 px-3 py-2 text-sm font-medium text-cyan-50 transition hover:bg-cyan-300/30"
                            >
                                Create User
                            </a>
                        @endif
                        <a
                            href="{{ route('filament.admin.pages.bi-dashboard') }}"
                            class="inline-flex items-center rounded-lg border border-green-300/50 bg-green-300/20 px-3 py-2 text-sm font-medium text-green-50 transition hover:bg-green-300/30"
                        >
                            BI Dashboard
                        </a>
                        <a
                            href="{{ route('filament.admin.pages.compliance-dashboard') }}"
                            class="inline-flex items-center rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm font-medium text-white transition hover:bg-white/20"
                        >
                            Compliance
                        </a>
                    </div>
                </div>
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Executive Snapshot</h3>
                    <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">KPI</span>
                </div>

                <x-filament-widgets::widgets
                    :widgets="$this->getExecutiveWidgets()"
                    :columns="$this->getExecutiveColumns()"
                />
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Live Mobility Map</h3>
                    <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Realtime</span>
                </div>

                <x-filament-widgets::widgets
                    :widgets="$this->getMapWidgets()"
                    :columns="$this->getMapColumns()"
                />
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Advanced Analytics</h3>
                    <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Charts and Diagram</span>
                </div>

                <x-filament-widgets::widgets
                    :widgets="$this->getChartWidgets()"
                    :columns="$this->getChartColumns()"
                />
            </section>

            <section class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Operational Tables and Logs</h3>
                    <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Actionable</span>
                </div>

                <x-filament-widgets::widgets
                    :widgets="$this->getOperationalTableWidgets()"
                    :columns="$this->getOperationalTableColumns()"
                />
            </section>
        </div>
    </x-filament-panels::page>
</div>
