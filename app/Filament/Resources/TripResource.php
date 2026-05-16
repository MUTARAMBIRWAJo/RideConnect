<?php

namespace App\Filament\Resources;

use App\Domain\Ride\RidePolicy;
use App\Filament\Resources\TripResource\Pages;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Ride;
use App\Models\Trip;
use App\Services\PassengerRegistrationService;
use App\Services\RuraTariffService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Trips';

    protected static ?string $modelLabel = 'Trip';

    protected static ?string $pluralModelLabel = 'Trips';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Trip Details')
                    ->schema([
                        Forms\Components\Select::make('transport_type')
                            ->label('Transport Type')
                            ->options([
                                'BUS' => '🚌 Bus (Public Transport)',
                                'CAR' => '🚗 Private Car',
                                'MOTORCYCLE' => '🏍 Motorcycle',
                            ])
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set) => $set('ride_id', null))
                            ->helperText('Filter rides by transport type to find eligible options.'),
                        Forms\Components\Select::make('ride_id')
                            ->label('Select Ride')
                            ->searchable()
                            ->live()
                            ->required()
                            ->options(function (callable $get): array {
                                $transportType = $get('transport_type');

                                return Ride::query()
                                    ->when($transportType, function (EloquentBuilder $query, $type): EloquentBuilder {
                                        return $query->where('transport_type', $type);
                                    })
                                    ->orderBy('origin_address', 'asc')
                                    ->orderBy('destination_address', 'asc')
                                    ->get()
                                    ->mapWithKeys(fn (Ride $ride): array => [
                                        $ride->id => sprintf(
                                            '%s → %s | %s',
                                            $ride->origin_address ?? 'Unknown',
                                            $ride->destination_address ?? 'Unknown',
                                            $ride->transport_type
                                        ),
                                    ])
                                    ->all();
                            })
                            ->helperText('Search and select a ride to check eligibility. Rides are sorted alphabetically by origin and destination.')
                            ->rule(function ($get) {
                                $rideId = $get('ride_id');

                                if (! $rideId) {
                                    return null;
                                }

                                $ride = Ride::find($rideId);

                                if (! $ride) {
                                    return null;
                                }

                                return RidePolicy::canRequestTrip($ride)
                                    ? null
                                    : 'Selected ride cannot be used for trip requests. Choose an on-demand ride.';
                            })
                            ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                                if (! $state) {
                                    return;
                                }

                                $ride = Ride::query()->with('driver.vehicles')->find((int) $state);

                                if (! $ride) {
                                    return;
                                }

                                $fare = app(RuraTariffService::class)->lookupTariff(
                                    null,
                                    $ride->origin_address,
                                    $ride->destination_address,
                                    $ride->corridor?->name
                                );

                                $set('fare', (float) ($fare['fare_rwf'] ?? $ride->price_per_seat ?? 0));

                                if ($ride->driver?->id) {
                                    $set('driver_id', $ride->driver_id);
                                }

                                if (! $get('pickup_location')) {
                                    $set('pickup_location', $ride->origin_address);
                                    $set('pickup_lat', $ride->origin_lat);
                                    $set('pickup_lng', $ride->origin_lng);
                                }

                                if (! $get('dropoff_location')) {
                                    $set('dropoff_location', $ride->destination_address);
                                    $set('dropoff_lat', $ride->destination_lat);
                                    $set('dropoff_lng', $ride->destination_lng);
                                }
                            }),
                        Forms\Components\Placeholder::make('ride_trip_rule')
                            ->label('Ride Rule')
                            ->content(function ($get) {
                                $rideId = $get('ride_id');

                                if (! $rideId) {
                                    return 'Select a ride to see trip eligibility.';
                                }

                                $ride = Ride::find($rideId);

                                if (! $ride) {
                                    return 'Selected ride not found.';
                                }

                                return sprintf(
                                    'Ride %s (%s) is %s for trip requests.',
                                    $ride->id,
                                    $ride->transport_type,
                                    RidePolicy::canRequestTrip($ride) ? 'eligible' : 'not eligible'
                                );
                            })
                            ->columnSpanFull(),
                        Forms\Components\Select::make('booking_id')
                            ->relationship(
                                name: 'booking',
                                titleAttribute: 'id',
                                modifyQueryUsing: fn (EloquentBuilder $query): EloquentBuilder => $query->orderByDesc('id')
                            )
                            ->getOptionLabelFromRecordUsing(fn (Booking $record): string => sprintf(
                                '#%d | %s %s | %s',
                                $record->id,
                                $record->pickup_address ?? 'Pickup',
                                $record->dropoff_address ? ('-> '.$record->dropoff_address) : '',
                                $record->status ?? 'PENDING'
                            ))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                if (! $state) {
                                    return;
                                }

                                $booking = Booking::query()->with('ride')->find((int) $state);

                                if (! $booking) {
                                    return;
                                }

                                $set('fare', (float) ($booking->total_price ?? 0));

                                if ($booking->ride_id) {
                                    $set('ride_id', $booking->ride_id);
                                }
                            }),
                        Forms\Components\Select::make('passenger_id')
                            ->label('Passenger')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return MobileUser::query()
                                    ->where(function (Builder $query) use ($search): void {
                                        $query->where('first_name', 'ilike', "%{$search}%")
                                            ->orWhere('last_name', 'ilike', "%{$search}%")
                                            ->orWhere('email', 'ilike', "%{$search}%")
                                            ->orWhere('phone', 'ilike', "%{$search}%");
                                    })
                                    ->select(['id', 'first_name', 'last_name', 'phone'])
                                    ->orderBy('first_name')
                                    ->limit(10)
                                    ->get()
                                    ->mapWithKeys(fn (MobileUser $record): array => [
                                        $record->id => trim($record->full_name.' | '.$record->phone),
                                    ])
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $passenger = MobileUser::find($value);

                                return $passenger?->full_name;
                            })
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Passenger Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('phone')
                                    ->tel()
                                    ->required()
                                    ->maxLength(20),
                                Forms\Components\Select::make('delivery_channel')
                                    ->label('Send Password Via')
                                    ->options([
                                        'email' => 'Email',
                                        'sms' => 'SMS',
                                        'whatsapp' => 'WhatsApp',
                                    ])
                                    ->default('email')
                                    ->required(),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $user = app(PassengerRegistrationService::class)->createOrUpdatePassenger(
                                    (string) ($data['name'] ?? ''),
                                    (string) ($data['email'] ?? ''),
                                    (string) ($data['phone'] ?? ''),
                                    (string) ($data['delivery_channel'] ?? 'email')
                                );

                                return (int) $user->mobile_user_id;
                            })
                            ->required(),
                        Forms\Components\Select::make('driver_id')
                            ->label('Assigned Driver')
                            ->searchable()
                            ->reactive()
                            ->required()
                            ->disabled(fn ($get): bool => (bool) $get('ride_id'))
                            ->helperText(fn ($get): string => $get('ride_id')
                                ? 'Driver is auto-assigned from the selected ride. Remove the ride to choose a different driver.'
                                : 'Select a driver manually if no ride is selected.')
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
                                        $label = trim(($driver->user?->name ?? 'Driver #'.$driver->id).' - '.($driver->license_plate ?? 'No plate'));

                                        return [$driver->id => $label];
                                    })
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                $driver = Driver::with('user')->find($value);
                                if (! $driver) {
                                    return null;
                                }

                                return trim(($driver->user?->name ?? 'Driver #'.$driver->id).' - '.($driver->license_plate ?? 'No plate'));
                            }),
                        Forms\Components\Placeholder::make('driver_summary')
                            ->label('Driver Information')
                            ->content(function ($get) {
                                $rideId = $get('ride_id');
                                if (! $rideId) {
                                    return 'Select a ride to auto-assign a driver, or choose one manually.';
                                }

                                $ride = Ride::with('driver.user')->find($rideId);
                                if (! $ride) {
                                    return 'Selected ride not found.';
                                }

                                if (! $ride->driver) {
                                    return 'No driver is assigned to the selected ride.';
                                }

                                return sprintf(
                                    'Auto-assigned driver: %s • Plate: %s',
                                    $ride->driver->user?->name ?? 'Driver #'.$ride->driver->id,
                                    $ride->driver->license_plate ?? 'Unknown'
                                );
                            })
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('fare')
                            ->label('Fare')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Automatically set from the selected booking or ride. Keep this value if you override manually.')
                            ->live(onBlur: true),
                        Forms\Components\Select::make('pickup_map_point')
                            ->label('Pickup Map Point')
                            ->options(fn (): array => collect(config('ride.map_points', []))
                                ->mapWithKeys(fn (array $point, string $key): array => [$key => $point['label'] ?? $key])
                                ->all())
                            ->searchable()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $point = config('ride.map_points.'.(string) $state);

                                if (! is_array($point)) {
                                    return;
                                }

                                $set('pickup_location', $point['label'] ?? null);
                                $set('pickup_lat', $point['lat'] ?? null);
                                $set('pickup_lng', $point['lng'] ?? null);
                            }),
                        Forms\Components\View::make('filament.forms.components.address-autocomplete')
                            ->viewData([
                                'addressField' => 'pickup_location',
                                'latField' => 'pickup_lat',
                                'lngField' => 'pickup_lng',
                                'label' => 'Pickup Location',
                                'placeholder' => 'Enter pickup address...',
                            ])
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('pickup_location')
                            ->hidden()
                            ->required(),
                        Forms\Components\TextInput::make('pickup_lat')
                            ->hidden()
                            ->required(),
                        Forms\Components\TextInput::make('pickup_lng')
                            ->hidden()
                            ->required(),
                        Forms\Components\Select::make('dropoff_map_point')
                            ->label('Dropoff Map Point')
                            ->options(fn (): array => collect(config('ride.map_points', []))
                                ->mapWithKeys(fn (array $point, string $key): array => [$key => $point['label'] ?? $key])
                                ->all())
                            ->searchable()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $point = config('ride.map_points.'.(string) $state);

                                if (! is_array($point)) {
                                    return;
                                }

                                $set('dropoff_location', $point['label'] ?? null);
                                $set('dropoff_lat', $point['lat'] ?? null);
                                $set('dropoff_lng', $point['lng'] ?? null);
                            }),
                        Forms\Components\View::make('filament.forms.components.address-autocomplete')
                            ->viewData([
                                'addressField' => 'dropoff_location',
                                'latField' => 'dropoff_lat',
                                'lngField' => 'dropoff_lng',
                                'label' => 'Dropoff Location',
                                'placeholder' => 'Enter dropoff address...',
                            ])
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('dropoff_location')
                            ->hidden()
                            ->required(),
                        Forms\Components\TextInput::make('dropoff_lat')
                            ->hidden()
                            ->required(),
                        Forms\Components\TextInput::make('dropoff_lng')
                            ->hidden()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'PENDING' => 'Pending',
                                'ACCEPTED' => 'Accepted',
                                'STARTED' => 'Started',
                                'COMPLETED' => 'Completed',
                                'CANCELLED' => 'Cancelled',
                            ])
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Timing')
                    ->schema([
                        Forms\Components\DateTimePicker::make('requested_at'),
                        Forms\Components\DateTimePicker::make('started_at'),
                        Forms\Components\DateTimePicker::make('completed_at'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('passenger.full_name')
                    ->label('Passenger')
                    ->sortable(),
                Tables\Columns\TextColumn::make('driver_id')
                    ->label('Driver ID')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('ride.transport_type')
                    ->label('Transport')
                    ->getStateUsing(fn (Trip $record): ?string => $record->ride?->transport_type)
                    ->colors([
                        'BUS' => 'success',
                        'CAR' => 'primary',
                        'MOTORCYCLE' => 'warning',
                        'default' => 'gray',
                    ]),
                Tables\Columns\BadgeColumn::make('ride.travel_mode')
                    ->label('Mode')
                    ->getStateUsing(fn (Trip $record): ?string => $record->ride?->travel_mode)
                    ->colors([
                        'SCHEDULED' => 'primary',
                        'ON_DEMAND' => 'success',
                        'default' => 'gray',
                    ]),
                Tables\Columns\BadgeColumn::make('ride.allowed_flow')
                    ->label('Passenger Flow')
                    ->getStateUsing(fn (Trip $record): string => $record->ride ? RidePolicy::getAllowedFlow($record->ride) : RidePolicy::FLOW_NONE)
                    ->colors([
                        RidePolicy::FLOW_BOOKING_ONLY => 'primary',
                        RidePolicy::FLOW_TRIP_ONLY => 'success',
                        RidePolicy::FLOW_BOTH => 'warning',
                        RidePolicy::FLOW_NONE => 'danger',
                    ]),
                Tables\Columns\TextColumn::make('pickup_location')
                    ->label('Pickup Location')
                    ->limit(30),
                Tables\Columns\TextColumn::make('dropoff_location')
                    ->label('Dropoff Location')
                    ->limit(30),
                Tables\Columns\TextColumn::make('pickup_zone')
                    ->label('Pickup Zone'),
                Tables\Columns\TextColumn::make('dropoff_zone')
                    ->label('Dropoff Zone'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower(trim($state))) {
                        'completed' => 'success',
                        'started' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed At')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // Add bulk actions if needed
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrips::route('/'),
            'create' => Pages\CreateTrip::route('/create'),
            'view' => Pages\ViewTrip::route('/{record}'),
            'edit' => Pages\EditTrip::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        $role = auth()->user()?->role?->value ?? auth()->user()?->role;

        return in_array($role, ['OFFICER', 'ADMIN', 'SUPER_ADMIN'], true);
    }

    public static function canViewAny(): bool
    {
        $role = auth()->user()?->role?->value ?? auth()->user()?->role;

        return in_array($role, ['OFFICER', 'ADMIN', 'SUPER_ADMIN'], true);
    }

    public static function canEdit(Model $record): bool
    {
        return static::canCreate();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
