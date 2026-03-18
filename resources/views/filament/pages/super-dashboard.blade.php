<x-filament-panels::page>
    <div class="space-y-6">
        <section class="rounded-2xl border border-gray-200 bg-white/90 p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900/70">
            @php($slowModeEnabled = (bool) config('dashboard.performance.slow_mode', false))
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Super Admin Command Center</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                        Real-time operations, AI telemetry, and BI insights in one responsive workspace.
                    </p>
                    <div class="mt-3">
                        <span
                            title="Uses low-bandwidth polling/lazy profile"
                            aria-label="Uses low-bandwidth polling/lazy profile"
                            class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold tracking-wide
                            {{ $slowModeEnabled
                                ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                : 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' }}"
                        >
                            Slow Mode: {{ $slowModeEnabled ? 'ON' : 'OFF' }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a
                        href="{{ route('filament.admin.pages.bi-dashboard') }}"
                        class="inline-flex items-center rounded-lg border border-green-300 px-3 py-2 text-sm font-medium text-green-800 transition hover:bg-green-50 dark:border-green-700 dark:text-green-300 dark:hover:bg-green-900/30"
                    >
                        Open BI Dashboard
                    </a>
                    <a
                        href="{{ route('filament.admin.pages.compliance-dashboard') }}"
                        class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800"
                    >
                        Compliance Dashboard
                    </a>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Operational Monitoring</h3>
                <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Live</span>
            </div>

            <x-filament-widgets::widgets
                :widgets="$this->getOperationalWidgets()"
                :columns="$this->getColumns()"
            />
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Business Intelligence Integrations</h3>
                <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Analytics</span>
            </div>

            <x-filament-widgets::widgets
                :widgets="$this->getIntelligenceWidgets()"
                :columns="$this->getIntelligenceColumns()"
            />
        </section>
    </div>
</x-filament-panels::page>
