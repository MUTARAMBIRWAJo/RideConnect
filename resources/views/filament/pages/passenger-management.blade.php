<x-filament-panels::page>
    <div class="space-y-4">
        <x-filament::section>
            <x-slot name="heading">Passenger Management</x-slot>
            <x-slot name="description">Monitor customer history and investigate suspicious booking behavior.</x-slot>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
