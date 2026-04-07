<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Accountant Dashboard</x-slot>
            <p>Track revenue, payments, and financial metrics with comprehensive reporting and analytics.</p>
        </x-filament::section>

        <x-filament-widgets::widgets
            :columns="$this->getColumns()"
            :data="$this->getWidgetData()"
            :widgets="$this->getVisibleWidgets()"
        />
    </div>
</x-filament-panels::page>
