<?php

namespace App\Filament\Resources;

use App\Domain\Ride\RidePolicy;
use App\Enums\UserRole;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use App\Services\PassengerRegistrationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Live Operations';

    protected static ?string $navigationLabel = 'Bookings';

    protected static ?string $modelLabel = 'Booking';

    protected static ?string $pluralModelLabel = 'Bookings';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Passenger')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $search): array {
                        return User::query()
                            ->where('role', UserRole::PASSENGER->value)
                            ->where(function (Builder $query) use ($search): void {
                                $query->where('name', 'ilike', "%{$search}%")
                                    ->orWhere('email', 'ilike', "%{$search}%")
                                    ->orWhere('phone', 'ilike', "%{$search}%");
                            })
                            ->select(['id', 'name'])
                            ->orderBy('name')
                            ->limit(10)
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->getOptionLabelUsing(function ($value): ?string {
                        $user = User::find($value);

                        return $user?->name;
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

                        return (int) $user->id;
                    })
                    ->searchable()
                    ->required(),
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
                                    '%s → %s | %s (%s)',
                                    $ride->origin_address ?? 'Unknown',
                                    $ride->destination_address ?? 'Unknown',
                                    $ride->transport_type,
                                    number_format((float) $ride->price_per_seat, 0).' '.($ride->currency ?? 'RWF')
                                ),
                            ])
                            ->all();
                    })
                    ->helperText('Only SCHEDULED rides can be booked. Trip requests go through the Trips panel.')
                    ->rules(function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        if (! $value) {
                            return;
                        }

                        $ride = Ride::query()->find((int) $value);

                        if (! $ride) {
                            $fail('The selected ride no longer exists.');
                            return;
                        }

                        $transportType = $get('transport_type') ?? $ride->transport_type;

                        if (! RidePolicy::canBook($ride)) {
                            $fail(
                                sprintf(
                                    'Ride "%s – %s" is %s and cannot be booked. '
                                    . 'Only SCHEDULED rides with an active route support booking.',
                                    $ride->origin_address,
                                    $ride->destination_address,
                                    strtoupper((string) ($ride->travel_mode ?? 'unknown'))
                                )
                            );
                        }
                    })
                    ->columnSpanFull(),
                Forms\Components\Placeholder::make('ride_booking_rule')
                    ->label('Ride Rule')
                    ->content(function ($get) {
                        $rideId = $get('ride_id');

                        if (! $rideId) {
                            return 'Select a ride to see booking eligibility.';
                        }

                        $ride = Ride::find($rideId);

                        if (! $ride) {
                            return 'Selected ride not found.';
                        }

                        return sprintf(
                            'Ride %s (%s) is %s for booking.',
                            $ride->id,
                            $ride->transport_type,
                            RidePolicy::canBook($ride) ? 'eligible' : 'not eligible'
                        );
                    })
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('seats_booked')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('total_price')
                    ->required()
                    ->numeric()
                    ->step(0.01)
                    ->helperText(function ($get) {
                        // Suggest legal fare using RuraTariffService
                        $rideId = $get('ride_id');
                        $seats = $get('seats_booked') ?: 1;
                        if (! $rideId) {
                            return null;
                        }
                        $ride = \App\Models\Ride::find($rideId);
                        if (! $ride) {
                            return null;
                        }
                        $origin = ['lat' => $ride->origin_lat, 'lng' => $ride->origin_lng];
                        $destination = ['lat' => $ride->destination_lat, 'lng' => $ride->destination_lng];
                        $zoneService = app(\App\Services\RuraZoneService::class);
                        $tariffService = app(\App\Services\RuraTariffService::class);
                        $originZone = $zoneService->getZoneForCoordinates($origin['lat'], $origin['lng']);
                        $destinationZone = $zoneService->getZoneForCoordinates($destination['lat'], $destination['lng']);
                        $tariffRow = $tariffService->lookupTariff(null, $originZone, $destinationZone, null);
                        $legalFare = is_array($tariffRow) ? (float) ($tariffRow['fare_rwf'] ?? 0) : 0;
                        if ($legalFare > 0) {
                            $totalLegal = $legalFare * $seats;

                            return 'RURA Legal Fare: RWF '.number_format($totalLegal, 2);
                        }

                        return 'No RURA tariff found for this route.';
                    })
                    ->validationMessages([
                        'rura_compliance' => 'Entered fare does not match RURA legal tariff for this route.',
                    ])
                    ->rules(function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                        // Validate against RURA tariff
                        $rideId = $get('ride_id');
                        $seats = $get('seats_booked') ?: 1;
                        $entered = $value;

                        if (! $rideId || ! $entered) {
                            return;
                        }

                        $ride = \App\Models\Ride::find((int) $rideId);
                        if (! $ride) {
                            return;
                        }

                        $origin = ['lat' => $ride->origin_lat, 'lng' => $ride->origin_lng];
                        $destination = ['lat' => $ride->destination_lat, 'lng' => $ride->destination_lng];
                        $zoneService = app(\App\Services\RuraZoneService::class);
                        $tariffService = app(\App\Services\RuraTariffService::class);
                        $originZone = $zoneService->getZoneForCoordinates($origin['lat'], $origin['lng']);
                        $destinationZone = $zoneService->getZoneForCoordinates($destination['lat'], $destination['lng']);
                        $tariffRow = $tariffService->lookupTariff(null, $originZone, $destinationZone, null);
                        $legalFare = is_array($tariffRow) ? (float) ($tariffRow['fare_rwf'] ?? 0) : 0;

                        if ($legalFare > 0) {
                            $totalLegal = $legalFare * $seats;

                            if (abs((float) $entered - $totalLegal) >= 0.01) {
                                $fail('rura_compliance');
                            }
                        }
                    }),
                /* ================================================================
                     LOCATION INPUTS — exclusive search-or-map UX for Booking form
                     ================================================================ */
                Forms\Components\View::make('filament.forms.components.location-input')
                    ->viewData([
                        'addressField'   => 'pickup_address',
                        'latField'       => 'pickup_lat',
                        'lngField'       => 'pickup_lng',
                        'placeNameField' => null,           // booking table has no place_name column
                        'label'          => 'Pickup Location',
                        'placeholder'    => 'Search pickup by name, or switch to map mode…',
                    ])
                    ->columnSpanFull(),
                Forms\Components\Hidden::make('pickup_address'),
                Forms\Components\Hidden::make('pickup_lat'),
                Forms\Components\Hidden::make('pickup_lng'),

                Forms\Components\View::make('filament.forms.components.location-input')
                    ->viewData([
                        'addressField'   => 'dropoff_address',
                        'latField'       => 'dropoff_lat',
                        'lngField'       => 'dropoff_lng',
                        'placeNameField' => null,           // booking table has no place_name column
                        'label'          => 'Dropoff Location',
                        'placeholder'    => 'Search dropoff by name, or switch to map mode…',
                    ])
                    ->columnSpanFull(),
                Forms\Components\Hidden::make('dropoff_address'),
                Forms\Components\Hidden::make('dropoff_lat'),
                Forms\Components\Hidden::make('dropoff_lng'),

                Forms\Components\Textarea::make('special_requests'),
                Forms\Components\DateTimePicker::make('confirmed_at'),
                Forms\Components\DateTimePicker::make('cancelled_at'),
                Forms\Components\TextInput::make('cancellation_reason'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ride.origin_address')
                    ->searchable()
                    ->limit(20),
                Tables\Columns\TextColumn::make('ride.destination_address')
                    ->searchable()
                    ->limit(20),
                Tables\Columns\BadgeColumn::make('ride.transport_type')
                    ->label('Transport')
                    ->getStateUsing(fn (Booking $record): ?string => $record->ride?->transport_type)
                    ->colors([
                        'BUS' => 'success',
                        'CAR' => 'primary',
                        'MOTORCYCLE' => 'warning',
                        'default' => 'gray',
                    ]),
                Tables\Columns\BadgeColumn::make('ride.travel_mode')
                    ->label('Mode')
                    ->getStateUsing(fn (Booking $record): ?string => $record->ride?->travel_mode)
                    ->colors([
                        'SCHEDULED' => 'primary',
                        'ON_DEMAND' => 'success',
                        'default' => 'gray',
                    ]),
                Tables\Columns\BadgeColumn::make('ride.allowed_flow')
                    ->label('Passenger Flow')
                    ->getStateUsing(fn (Booking $record): string => $record->ride ? RidePolicy::getAllowedFlow($record->ride) : 'NONE')
                    ->colors([
                        RidePolicy::FLOW_BOOKING_ONLY => 'primary',
                        RidePolicy::FLOW_TRIP_ONLY => 'success',
                        RidePolicy::FLOW_BOTH => 'warning',
                        RidePolicy::FLOW_NONE => 'danger',
                    ]),
                Tables\Columns\TextColumn::make('seats_booked')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->money('RWF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'CONFIRMED' => 'success',
                        'PENDING' => 'warning',
                        'CANCELLED' => 'danger',
                        'COMPLETED' => 'info',
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
                        'PENDING' => 'Pending',
                        'CONFIRMED' => 'Confirmed',
                        'CANCELLED' => 'Cancelled',
                        'COMPLETED' => 'Completed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('print_receipt')
                        ->label('Print Receipt')
                        ->color('success')
                        ->icon('heroicon-o-printer')
                        ->visible(fn (): bool => auth()->user()?->isManager() ?? false)
                        ->action(function (Booking $record) {
                            $pdf = app(\App\Services\BookingReceiptDeliveryService::class)->generatePdfBinary($record);

                            return response()->streamDownload(fn () => print ($pdf), "booking-receipt-{$record->id}.pdf");
                        }),

                    Tables\Actions\Action::make('email_receipt')
                        ->label('Email Receipt')
                        ->icon('heroicon-o-envelope')
                        ->color('info')
                        ->visible(fn (): bool => auth()->user()?->isManager() ?? false)
                        ->action(function (Booking $record) {
                            try {
                                app(\App\Services\BookingReceiptDeliveryService::class)->sendToEmail($record);
                                \Filament\Notifications\Notification::make()->title('Email sent successfully')->success()->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()->title('Failed to send email')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Tables\Actions\Action::make('whatsapp_receipt')
                        ->label('WhatsApp Receipt')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->color('success')
                        ->visible(fn (): bool => auth()->user()?->isManager() ?? false)
                        ->action(function (Booking $record) {
                            try {
                                app(\App\Services\BookingReceiptDeliveryService::class)->sendToWhatsApp($record);
                                \Filament\Notifications\Notification::make()->title('WhatsApp message sent successfully')->success()->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()->title('Failed to send WhatsApp')->body($e->getMessage())->danger()->send();
                            }
                        }),

                    Tables\Actions\Action::make('sms_receipt')
                        ->label('SMS Receipt')
                        ->icon('heroicon-o-device-phone-mobile')
                        ->color('primary')
                        ->visible(fn (): bool => auth()->user()?->isManager() ?? false)
                        ->action(function (Booking $record) {
                            try {
                                app(\App\Services\BookingReceiptDeliveryService::class)->sendToSms($record);
                                \Filament\Notifications\Notification::make()->title('SMS sent successfully')->success()->send();
                            } catch (\Exception $e) {
                                \Filament\Notifications\Notification::make()->title('Failed to send SMS')->body($e->getMessage())->danger()->send();
                            }
                        }),
                ])->label('Receipt')->icon('heroicon-o-document-text'),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Booking $record): bool => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Booking $record): bool => static::canDelete($record)),
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
            'index' => Pages\ListBookings::route('/'),
            'create' => Pages\CreateBooking::route('/create'),
            'edit' => Pages\EditBooking::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        $role = auth()->user()?->role?->value ?? auth()->user()?->role;

        return in_array($role, ['SUPER_ADMIN', 'ADMIN', 'OFFICER'], true)
            || (auth()->user()?->can('view rides') ?? false);
    }

    public static function canCreate(): bool
    {
        $role = auth()->user()?->role?->value ?? auth()->user()?->role;

        return in_array($role, ['SUPER_ADMIN', 'ADMIN', 'OFFICER'], true);
    }

    public static function canEdit(Model $record): bool
    {
        $role = auth()->user()?->role?->value ?? auth()->user()?->role;

        return in_array($role, ['SUPER_ADMIN', 'ADMIN', 'OFFICER'], true);
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->can('manage rides') ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
