<x-filament-panels::page class="fi-dashboard-page">
    <div>
        @if (method_exists($this, 'filtersForm'))
            <div>
                {{ $this->filtersForm }}
            </div>
        @endif

        <x-filament-widgets::widgets
            :columns="$this->getColumns()"
            :data="
                [
                    ...(property_exists($this, 'filters') ? ['filters' => $this->filters] : []),
                    ...$this->getWidgetData(),
                ]
            "
            :widgets="$this->getVisibleWidgets()"
        />
    </div>
</x-filament-panels::page>