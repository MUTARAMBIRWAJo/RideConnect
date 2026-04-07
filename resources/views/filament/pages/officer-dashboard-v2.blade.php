<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Officer Dashboard</x-slot>
            <p>Manage rides, support tickets, and passenger-driver matching from your operational hub.</p>
        </x-filament::section>

        <x-filament-widgets::widgets
            :columns="$this->getColumns()"
            :data="$this->getWidgetData()"
            :widgets="$this->getVisibleWidgets()"
        />
    </div>
</x-filament-panels::page>
