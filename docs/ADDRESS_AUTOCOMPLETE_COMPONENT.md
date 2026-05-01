# Address Autocomplete Component

This component provides Google Places API autocomplete functionality for Filament forms.

## Features

- Google Places API autocomplete with address suggestions
- Automatic population of latitude and longitude coordinates
- Restricted to Rwanda addresses for better relevance
- Handles existing data when editing records
- Graceful fallback when API key is not configured
- Integrates with Filament's form state management

## Usage

Add the component to your Filament form schema:

```php
use Filament\Forms\Components\View;
use Filament\Forms\Components\TextInput;

// In your form schema:
Forms\Components\View::make('filament.forms.components.address-autocomplete')
    ->viewData([
        'addressField' => 'pickup_address',
        'latField' => 'pickup_lat',
        'lngField' => 'pickup_lng',
        'label' => 'Pickup Location',
        'placeholder' => 'Enter pickup address...',
    ]),

// Add the corresponding hidden fields
Forms\Components\TextInput::make('pickup_address')
    ->hidden(),
Forms\Components\TextInput::make('pickup_lat')
    ->hidden(),
Forms\Components\TextInput::make('pickup_lng')
    ->hidden(),
```

## Parameters

- `addressField`: The field name for the address (default: 'pickup_address')
- `latField`: The field name for latitude (default: 'pickup_lat')
- `lngField`: The field name for longitude (default: 'pickup_lng')
- `label`: The label for the input (default: 'Pickup Location')
- `placeholder`: Placeholder text (default: 'Enter address...')

## Requirements

- Google Maps API key must be configured in `config/laramaps.php` or environment variables
- The component restricts results to Rwanda addresses
- Requires internet connection for Google Places API

## Alternative Custom Field Class

A custom field class is also available at `App\Filament\Forms\Components\AddressAutocomplete`:

```php
use App\Filament\Forms\Components\AddressAutocomplete;

AddressAutocomplete::make('pickup_location')
    ->addressField('pickup_address')
    ->latField('pickup_lat')
    ->lngField('pickup_lng')
    ->label('Pickup Location')
    ->placeholder('Enter pickup address...'),
```

Note: The custom field class approach is experimental and may require additional configuration.