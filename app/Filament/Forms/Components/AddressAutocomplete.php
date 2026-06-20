<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

class AddressAutocomplete extends Field
{
    protected string $view = 'filament.forms.components.address-autocomplete';

    protected string $addressField = 'pickup_address';

    protected string $latField = 'pickup_lat';

    protected string $lngField = 'pickup_lng';

    protected \Illuminate\Contracts\Support\Htmlable|\Closure|string|null $label = 'Pickup Location';

    protected string $placeholder = 'Enter address...';

    public function addressField(string $field): static
    {
        $this->addressField = $field;

        return $this;
    }

    public function latField(string $field): static
    {
        $this->latField = $field;

        return $this;
    }

    public function lngField(string $field): static
    {
        $this->lngField = $field;

        return $this;
    }

    public function placeholder(string $placeholder): static
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function getAddressField(): string
    {
        return $this->addressField;
    }

    public function getLatField(): string
    {
        return $this->latField;
    }

    public function getLngField(): string
    {
        return $this->lngField;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'addressField' => $this->getAddressField(),
            'latField' => $this->getLatField(),
            'lngField' => $this->getLngField(),
            'label' => $this->getLabel(),
            'placeholder' => $this->getPlaceholder(),
        ]);
    }
}
