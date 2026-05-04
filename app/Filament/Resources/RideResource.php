<?php

namespace App\Filament\Resources;

use App\Domain\Ride\RidePolicy;
use App\Filament\Resources\RideResource\Pages;
use App\Models\Corridor;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\TransportRoute;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RideResource extends Resource
{
    protected static ?string $model = Ride::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = 'Live Operations';

    protected static ?string $navigationLabel = 'Rides';

    protected static ?string $modelLabel = 'Ride';

    protected static ?string $pluralModelLabel = 'Rides';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('driver_id')
                    ->label('Driver')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return Driver::query()
                            ->whereHas('user', function (Builder $query) use ($search): void {
                                $query->where('name', 'ilike', "%{$search}%")
                                    ->orWhere('phone', 'ilike', "%{$search}%");
                            })
                            ->with('user')
                            ->limit(10)
                            ->get()
                            ->mapWithKeys(function (Driver $driver): array {
                                $label = trim(($driver->user?->name ?? 'Driver #' . $driver->id) . ' - ' . ($driver->license_plate ?? 'No plate'));

                                return [$driver->id => $label];
                            })
                            ->all();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $driver = Driver::with('user')->find($value);
                        if (! $driver) {
                            return null;
                        }

                        return trim(($driver->user?->name ?? 'Driver #' . $driver->id) . ' - ' . ($driver->license_plate ?? 'No plate'));
                    })
                    ->required(),
                Forms\Components\Select::make('vehicle_id')
                    ->relationship('vehicle', 'id'),
                Forms\Components\Select::make('transport_type')
                    ->label('Transport Type')
                    ->options([
                        Ride::TRANSPORT_BUS => 'Bus',
                        Ride::TRANSPORT_CAR => 'Car',
                        Ride::TRANSPORT_MOTORCYCLE => 'Motorcycle',
                    ])
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(function (?string $state, callable $set): void {
                        if ($state === Ride::TRANSPORT_BUS) {
                            $set('travel_mode', Ride::MODE_SCHEDULED);
                        }

                        if ($state === Ride::TRANSPORT_MOTORCYCLE) {
                            $set('travel_mode', Ride::MODE_ON_DEMAND);
                        }
                    })
                    ->helperText('BUS must be SCHEDULED, MOTORCYCLE must be ON_DEMAND, CAR can be either.'),
                Forms\Components\Select::make('corridor_id')
                    ->label('Corridor')
                    ->options(fn (): array => Corridor::query()
                        ->orderBy('code')
                        ->get()
                        ->mapWithKeys(fn (Corridor $corridor): array => [
                            $corridor->id => trim('Corridor ' . ($corridor->code ?? $corridor->id) . ' - ' . $corridor->name),
                        ])
                        ->all())
                    ->searchable()
                    ->visible(fn ($get): bool => $get('transport_type') === Ride::TRANSPORT_BUS)
                    ->required(fn ($get): bool => $get('transport_type') === Ride::TRANSPORT_BUS)
                    ->reactive()
                    ->afterStateUpdated(fn (callable $set) => $set('route_id', null))
                    ->helperText('Select the government corridor for BUS rides.'),
                Forms\Components\Select::make('route_id')
                    ->label('Route')
                    ->options(function (callable $get): array {
                        $corridorId = $get('corridor_id');

                        return TransportRoute::query()
                            ->when($corridorId, fn ($query, $value) => $query->where('corridor_id', $value))
                            ->where('is_active', true)
                            ->orderBy('route_code')
                            ->get()
                            ->mapWithKeys(fn (TransportRoute $route): array => [
                                $route->id => sprintf('%s - %s', $route->route_code, $route->name),
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->visible(fn ($get): bool => $get('transport_type') === Ride::TRANSPORT_BUS)
                    ->required(fn ($get): bool => $get('transport_type') === Ride::TRANSPORT_BUS)
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if (! $state) {
                            return;
                        }

                        $route = TransportRoute::query()->with('corridor')->find((int) $state);

                        if (! $route) {
                            return;
                        }

                        $set('corridor_id', $route->corridor_id);
                        $set('origin_address', $route->origin);
                        $set('destination_address', $route->destination);
                        $set('bus_number', $route->route_code);
                    })
                    ->helperText('Filtered by corridor. BUS rides must be tied to a route.'),
                Forms\Components\Select::make('travel_mode')
                    ->label('Travel Mode')
                    ->options([
                        Ride::MODE_SCHEDULED => 'Scheduled',
                        Ride::MODE_ON_DEMAND => 'On Demand',
                    ])
                    ->required()
                    ->reactive()
                    ->disabled(fn (?string $transportType): bool => in_array($transportType, [Ride::TRANSPORT_BUS, Ride::TRANSPORT_MOTORCYCLE], true))
                    ->helperText(function ($get): string {
                        $transportType = $get('transport_type');

                        if ($transportType === Ride::TRANSPORT_BUS) {
                            return 'Bus rides must be SCHEDULED.';
                        }

                        if ($transportType === Ride::TRANSPORT_MOTORCYCLE) {
                            return 'Motorcycle rides must be ON_DEMAND.';
                        }

                        return 'Car rides can be scheduled or on-demand.';
                    }),
                Forms\Components\View::make('filament.forms.components.address-autocomplete')
                    ->viewData([
                        'addressField' => 'origin_address',
                        'latField' => 'origin_lat',
                        'lngField' => 'origin_lng',
                        'label' => 'Origin Location',
                        'placeholder' => 'Enter origin address...',
                    ])
                    ->visible(fn ($get): bool => $get('transport_type') !== Ride::TRANSPORT_BUS)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('origin_address')
                    ->hidden(),
                Forms\Components\TextInput::make('origin_lat')
                    ->hidden(),
                Forms\Components\TextInput::make('origin_lng')
                    ->hidden(),
                Forms\Components\View::make('filament.forms.components.address-autocomplete')
                    ->viewData([
                        'addressField' => 'destination_address',
                        'latField' => 'destination_lat',
                        'lngField' => 'destination_lng',
                        'label' => 'Destination Location',
                        'placeholder' => 'Enter destination address...',
                    ])
                    ->visible(fn ($get): bool => $get('transport_type') !== Ride::TRANSPORT_BUS)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('destination_address')
                    ->hidden(),
                Forms\Components\TextInput::make('destination_lat')
                    ->hidden(),
                Forms\Components\TextInput::make('destination_lng')
                    ->hidden(),
                Forms\Components\TextInput::make('bus_number')
                    ->label('Bus Number')
                    ->visible(fn ($get): bool => $get('transport_type') === Ride::TRANSPORT_BUS)
                    ->required(fn ($get): bool => $get('transport_type') === Ride::TRANSPORT_BUS)
                    ->helperText('Auto-filled from the selected route, but can be reviewed here.'),
                Forms\Components\DateTimePicker::make('departure_time')
                    ->required(),
                Forms\Components\DateTimePicker::make('arrival_time_estimated'),
                Forms\Components\TextInput::make('available_seats')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('price_per_seat')
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->helperText(function ($get) {
                        // Suggest legal fare per seat using RuraTariffService
                        $originLat = $get('origin_lat');
                        $originLng = $get('origin_lng');
                        $destLat = $get('destination_lat');
                        $destLng = $get('destination_lng');
                        $vehicleType = $get('vehicle_type') ?? 'car';
                        $rideType = $get('ride_type') ?? 'local';
                        if (!$originLat || !$originLng || !$destLat || !$destLng) return null;
                        $zoneService = app(\App\Services\RuraZoneService::class);
                        $tariffService = app(\App\Services\RuraTariffService::class);
                        $originZone = $zoneService->getZoneForCoordinates((float) $originLat, (float) $originLng);
                        $destinationZone = $zoneService->getZoneForCoordinates((float) $destLat, (float) $destLng);
                        $tariffRow = $tariffService->lookupTariff(null, $originZone, $destinationZone, null);
                        $legalFare = is_array($tariffRow) ? (float) ($tariffRow['fare_rwf'] ?? 0) : 0;
                        if ($legalFare > 0) {
                            return 'RURA Legal Fare per seat: RWF ' . number_format($legalFare, 2);
                        }
                        return 'No RURA tariff found for this route.';
                    })
                    ->validationMessages([
                        'rura_compliance' => 'Entered fare does not match RURA legal tariff for this route.'
                    ])
                    ->rule(function ($get) {
                        // Validate against RURA tariff
                        $originLat = $get('origin_lat');
                        $originLng = $get('origin_lng');
                        $destLat = $get('destination_lat');
                        $destLng = $get('destination_lng');
                        $vehicleType = $get('vehicle_type') ?? 'car';
                        $rideType = $get('ride_type') ?? 'local';
                        $entered = $get('price_per_seat');
                        if (!$originLat || !$originLng || !$destLat || !$destLng || !$entered) return null;
                        $zoneService = app(\App\Services\RuraZoneService::class);
                        $tariffService = app(\App\Services\RuraTariffService::class);
                        $originZone = $zoneService->getZoneForCoordinates((float) $originLat, (float) $originLng);
                        $destinationZone = $zoneService->getZoneForCoordinates((float) $destLat, (float) $destLng);
                        $tariffRow = $tariffService->lookupTariff(null, $originZone, $destinationZone, null);
                        $legalFare = is_array($tariffRow) ? (float) ($tariffRow['fare_rwf'] ?? 0) : 0;
                        if ($legalFare > 0) {
                            return abs(((float) $entered) - $legalFare) < 0.01 ? null : 'rura_compliance';
                        }
                        return null;
                    }),
                Forms\Components\TextInput::make('currency')
                    ->required()
                    ->maxLength(3)
                    ->default('RWF'),
                Forms\Components\Textarea::make('description'),
                Forms\Components\Select::make('status')
                    ->options([
                        'available' => 'Available',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('available')
                    ->required(),
                Forms\Components\Select::make('ride_type')
                    ->label('Ride Type')
                    ->options([
                        Ride::TYPE_INTERCITY => '🌍 Intercity (Long distance)',
                        Ride::TYPE_LOCAL => '📍 Local (Within city)',
                    ])
                    ->required()
                    ->default(Ride::TYPE_LOCAL),
                Forms\Components\Checkbox::make('luggage_allowed'),
                Forms\Components\Checkbox::make('pets_allowed'),
                Forms\Components\Checkbox::make('smoking_allowed'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver.user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('transport_type')
                    ->label('Transport')
                    ->colors([
                        'BUS' => 'success',
                        'CAR' => 'primary',
                        'MOTORCYCLE' => 'warning',
                        'default' => 'gray',
                    ])
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('corridor.code')
                    ->label('Corridor')
                    ->getStateUsing(fn (Ride $record): ?string => $record->corridor?->code ? 'Corridor ' . $record->corridor->code : null)
                    ->colors([
                        'default' => 'gray',
                    ]),
                Tables\Columns\TextColumn::make('route.name')
                    ->label('Route')
                    ->getStateUsing(fn (Ride $record): ?string => $record->route?->name)
                    ->limit(35),
                Tables\Columns\TextColumn::make('bus_number')
                    ->label('Bus #')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('travel_mode')
                    ->label('Mode')
                    ->colors([
                        'SCHEDULED' => 'primary',
                        'ON_DEMAND' => 'success',
                        'default' => 'gray',
                    ])
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('ride_type')
                    ->label('Type')
                    ->colors([
                        Ride::TYPE_INTERCITY => 'info',
                        Ride::TYPE_LOCAL => 'success',
                        'default' => 'gray',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('origin_address')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('destination_address')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\BadgeColumn::make('allowed_flow')
                    ->label('Passenger Flow')
                    ->getStateUsing(fn (Ride $record): string => RidePolicy::getAllowedFlow($record))
                    ->colors([
                        RidePolicy::FLOW_BOOKING_ONLY => 'primary',
                        RidePolicy::FLOW_TRIP_ONLY => 'success',
                        RidePolicy::FLOW_BOTH => 'warning',
                        RidePolicy::FLOW_NONE => 'danger',
                    ]),
                Tables\Columns\TextColumn::make('departure_time')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_seats')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_per_seat')
                    ->money('RWF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'available', 'active' => 'success',
                        'scheduled' => 'primary',
                        'in_progress', 'started' => 'warning',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'available' => 'Available',
                        'active' => 'Active',
                        'scheduled' => 'Scheduled',
                        'in_progress' => 'In Progress',
                        'started' => 'Started',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                Tables\Filters\SelectFilter::make('transport_type')
                    ->label('Transport')
                    ->options([
                        'BUS' => 'Bus',
                        'CAR' => 'Car',
                        'MOTORCYCLE' => 'Motorcycle',
                    ]),
                Tables\Filters\SelectFilter::make('travel_mode')
                    ->label('Mode')
                    ->options([
                        'SCHEDULED' => 'Scheduled',
                        'ON_DEMAND' => 'On Demand',
                    ]),
                Tables\Filters\SelectFilter::make('ride_type')
                    ->options([
                        'intercity' => 'Intercity',
                        'local' => 'Local',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Ride $record): bool => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Ride $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('manage rides') ?? false),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRides::route('/'),
            'create' => Pages\CreateRide::route('/create'),
            'edit' => Pages\EditRide::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view rides') ?? false;
    }

    public static function canCreate(): bool
    {
        $role = auth()->user()?->role?->value ?? auth()->user()?->role;

        return in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);
    }

    public static function canEdit(Model $record): bool
    {
        $role = auth()->user()?->role?->value ?? auth()->user()?->role;

        return in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);
    }

    public static function canDelete(Model $record): bool
    {
        $role = auth()->user()?->role?->value ?? auth()->user()?->role;

        return in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
