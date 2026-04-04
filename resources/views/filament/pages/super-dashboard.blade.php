<x-filament-panels::page class="fi-dashboard-page">
    <div class="space-y-6">
        <div>
            <x-filament-widgets::widgets
                :columns="$this->getColumns()"
                :data="$this->getWidgetData()"
                :widgets="$this->getVisibleWidgets()"
            />
        </div>
    </div>
</x-filament-panels::page>