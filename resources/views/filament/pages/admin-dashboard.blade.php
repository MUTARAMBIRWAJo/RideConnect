<x-filament-panels::page>
    <div class="space-y-6">
        @if ($this->getHeaderWidgets())
            <x-filament-widgets::widgets
                :widgets="$this->getHeaderWidgets()"
                :columns="['default' => 1, 'md' => 2, 'lg' => 2, 'xl' => 2, '2xl' => 2]"
            />
        @endif

        @if ($this->getFooterWidgets())
            <x-filament-widgets::widgets
                :widgets="$this->getFooterWidgets()"
                :columns="['default' => 1, 'md' => 1, 'lg' => 1, 'xl' => 1, '2xl' => 1]"
            />
        @endif
    </div>
</x-filament-panels::page>