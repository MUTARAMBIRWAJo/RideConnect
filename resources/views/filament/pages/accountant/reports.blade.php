<x-filament-panels::page>
    <div class="space-y-6" wire:poll.60s>
        <!-- Hero Section -->
        <section class="rounded-xl border-0 bg-gradient-to-r from-purple-600 to-pink-600 p-6 text-white shadow-lg">
            <p class="text-xs font-semibold uppercase tracking-wider text-purple-100">Reporting & Export</p>
            <h1 class="mt-1 text-2xl font-semibold sm:text-3xl">Financial Reports</h1>
            <p class="mt-2 max-w-2xl text-sm text-purple-100 sm:text-base">
                Generate comprehensive financial reports and export data for tax, accounting, and compliance purposes.
            </p>
        </section>

        <!-- Available Reports -->
        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            @foreach ($availableReports as $report)
                <div class="rounded-xl border-2 border-slate-200 bg-white p-5 shadow-sm hover:shadow-md hover:border-purple-300 transition cursor-pointer">
                    <div class="text-3xl mb-3">
                        @if (str_contains($report['action'], 'daily'))
                            📅
                        @elseif (str_contains($report['action'], 'monthly'))
                            📆
                        @elseif (str_contains($report['action'], 'settlement'))
                            👥
                        @else
                            📊
                        @endif
                    </div>
                    <h3 class="text-sm font-semibold text-slate-900">{{ $report['name'] }}</h3>
                    <p class="mt-2 text-xs text-slate-600">{{ $report['description'] }}</p>
                    <div class="mt-4 flex gap-2">
                        <button type="button" wire:click="generateReport('{{ $report['action'] }}')" class="flex-1 px-3 py-2 bg-purple-100 text-purple-700 text-xs font-medium rounded hover:bg-purple-200 transition text-center">
                            Generate
                        </button>
                        <button type="button" wire:click="exportCSV('{{ $report['action'] }}')" class="px-3 py-2 bg-slate-100 text-slate-700 text-xs font-medium rounded hover:bg-slate-200 transition" title="Download CSV">
                            📥
                        </button>
                    </div>
                </div>
            @endforeach
        </section>

        <!-- Quick Reports Section -->
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-base font-semibold text-slate-900 mb-4">Quick Report Generator</h2>
            <div class="space-y-4">
                <!-- Daily Report -->
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-semibold text-slate-900">Daily Report</h3>
                            <p class="text-xs text-slate-600 mt-1">Transaction summary, revenue, and commission for a specific date</p>
                        </div>
                        <span class="text-sm text-slate-500">📅 {{ now()->format('M d, Y') }}</span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="generateReport('daily')" class="px-4 py-2 bg-blue-100 text-blue-700 text-sm rounded hover:bg-blue-200 transition">View Report</button>
                        <button type="button" wire:click="exportPDF('daily')" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded hover:bg-slate-200 transition">Download PDF</button>
                        <button type="button" wire:click="exportCSV('daily')" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded hover:bg-slate-200 transition">Export CSV</button>
                    </div>
                </div>

                <!-- Monthly Report -->
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-semibold text-slate-900">Monthly Report</h3>
                            <p class="text-xs text-slate-600 mt-1">Comprehensive financial overview for the entire month</p>
                        </div>
                        <span class="text-sm text-slate-500">📆 {{ now()->format('F Y') }}</span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="generateReport('monthly')" class="px-4 py-2 bg-blue-100 text-blue-700 text-sm rounded hover:bg-blue-200 transition">View Report</button>
                        <button type="button" wire:click="exportPDF('monthly')" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded hover:bg-slate-200 transition">Download PDF</button>
                        <button type="button" wire:click="exportCSV('monthly')" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded hover:bg-slate-200 transition">Export CSV</button>
                    </div>
                </div>

                <!-- Driver Settlement -->
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-semibold text-slate-900">Driver Settlement Report</h3>
                            <p class="text-xs text-slate-600 mt-1">Detailed breakdown of all driver payouts and commissions</p>
                        </div>
                        <span class="text-sm text-slate-500">👥 All Drivers</span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="generateReport('settlement')" class="px-4 py-2 bg-blue-100 text-blue-700 text-sm rounded hover:bg-blue-200 transition">View Report</button>
                        <button type="button" wire:click="exportPDF('settlement')" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded hover:bg-slate-200 transition">Download PDF</button>
                        <button type="button" wire:click="exportCSV('settlement')" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded hover:bg-slate-200 transition">Export CSV</button>
                    </div>
                </div>

                <!-- Tax Summary -->
                <div class="border border-slate-200 rounded-lg p-4">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-semibold text-slate-900">Tax Summary Report</h3>
                            <p class="text-xs text-slate-600 mt-1">Tax-relevant transactions and deduction calculations</p>
                        </div>
                        <span class="text-sm text-slate-500">💳 Tax Year</span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <button type="button" wire:click="generateReport('tax')" class="px-4 py-2 bg-blue-100 text-blue-700 text-sm rounded hover:bg-blue-200 transition">View Report</button>
                        <button type="button" wire:click="exportPDF('tax')" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded hover:bg-slate-200 transition">Download PDF</button>
                        <button type="button" wire:click="exportCSV('tax')" class="px-4 py-2 bg-slate-100 text-slate-700 text-sm rounded hover:bg-slate-200 transition">Export CSV</button>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs text-slate-600">
                Current report context:
                <span class="font-semibold text-slate-800">{{ strtoupper($reportType) }}</span>
                @if ($exportFormat !== 'none')
                    | Last export:
                    <span class="font-semibold text-slate-800">{{ strtoupper($exportFormat) }}</span>
                @endif
            </p>
        </section>

        <!-- Export Information -->
        <section class="rounded-xl border border-green-200 bg-green-50 p-4">
            <p class="text-xs text-green-900">
                💡 <strong>Report Information:</strong> All reports are generated server-side and can be exported to PDF or CSV format.
                Exported files are suitable for accounting software, tax preparation, and audit purposes.
            </p>
        </section>
    </div>
</x-filament-panels::page>
