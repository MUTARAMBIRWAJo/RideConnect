<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Dashboard widgets loaded via PHP class --}}
        <x-filament-widgets::widgets
            :columns="$this->getColumns()"
            :data="$this->getWidgetData()"
            :widgets="$this->getVisibleWidgets()"
        />
    </div>
</x-filament-panels::page>