<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section class="overflow-hidden border-0 bg-gradient-to-r from-emerald-600 to-teal-600 text-white shadow-lg">
            <div class="flex flex-col gap-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-teal-100">RideConnect Finance Center</p>
                <h1 class="text-2xl font-semibold sm:text-3xl">Accountant Dashboard</h1>
                <p class="max-w-2xl text-sm text-teal-100 sm:text-base">
                    Finance command page for reconciliations, payouts, and payment monitoring.
                </p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Finance Tools</x-slot>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @if (auth()->user()->can('view finances'))
                    <a href="{{ route('filament.admin.resources.payments.index') }}" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">Payments</a>
                    <a href="{{ route('filament.admin.resources.commissions.index') }}" class="rounded-xl border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-700">Commissions</a>
                    <a href="{{ route('filament.admin.resources.revenue.index') }}" class="rounded-xl border border-teal-200 bg-teal-50 p-4 text-sm text-teal-700">Revenue</a>
                @endif
                @if (\Illuminate\Support\Facades\Route::has('filament.admin.resources.driver-payouts.index') && auth()->user()->can('view finances'))
                    <a href="{{ route('filament.admin.resources.driver-payouts.index') }}" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700">Driver Payouts</a>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
