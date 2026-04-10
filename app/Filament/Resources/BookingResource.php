<?php

namespace App\Filament\Resources;

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
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
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
                Forms\Components\Select::make('ride_id')
                    ->relationship(
                        name: 'ride',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn (EloquentBuilder $query): EloquentBuilder => $query->orderByDesc('id')
                    )
                    ->getOptionLabelFromRecordUsing(fn (Ride $record): string => sprintf(
                        '#%d | %s -> %s | %s %s',
                        $record->id,
                        $record->origin_address ?? 'Unknown',
                        $record->destination_address ?? 'Unknown',
                        number_format((float) $record->price_per_seat, 0),
                        $record->currency ?? 'RWF'
                    ))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required(),
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
                        if (!$rideId) return null;
                        $ride = \App\Models\Ride::find($rideId);
                        if (!$ride) return null;
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
                            return 'RURA Legal Fare: RWF ' . number_format($totalLegal, 2);
                        }
                        return 'No RURA tariff found for this route.';
                    })
                    ->validationMessages([
                        'rura_compliance' => 'Entered fare does not match RURA legal tariff for this route.'
                    ])
                    ->rule(function ($get) {
                        // Validate against RURA tariff
                        $rideId = $get('ride_id');
                        $seats = $get('seats_booked') ?: 1;
                        $entered = $get('total_price');
                        if (!$rideId || !$entered) return null;
                        $ride = \App\Models\Ride::find($rideId);
                        if (!$ride) return null;
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
                            return abs(((float) $entered) - $totalLegal) < 0.01 ? null : 'rura_compliance';
                        }
                        return null;
                    }),
                Forms\Components\TextInput::make('currency')
                    ->required()
                    ->maxLength(3)
                    ->default('RWF'),
                Forms\Components\Select::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'CONFIRMED' => 'Confirmed',
                        'CANCELLED' => 'Cancelled',
                        'COMPLETED' => 'Completed',
                    ])
                    ->default('PENDING')
                    ->required(),
                Forms\Components\Select::make('pickup_map_point')
                    ->label('Pickup Map Point')
                    ->options(fn (): array => collect(config('ride.map_points', []))
                        ->mapWithKeys(fn (array $point, string $key): array => [$key => $point['label'] ?? $key])
                        ->all())
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $point = config('ride.map_points.' . (string) $state);

                        if (! is_array($point)) {
                            return;
                        }

                        $set('pickup_address', $point['label'] ?? null);
                        $set('pickup_lat', $point['lat'] ?? null);
                        $set('pickup_lng', $point['lng'] ?? null);
                    }),
                Forms\Components\View::make('filament.forms.components.location-map-picker')
                    ->viewData([
                        'label' => 'Pickup Location Map',
                        'latField' => 'pickup_lat',
                        'lngField' => 'pickup_lng',
                        'addressField' => 'pickup_address',
                    ])
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('pickup_address')
                    ->maxLength(255),
                Forms\Components\TextInput::make('pickup_lat')
                    ->numeric()
                    ->readOnly(),
                Forms\Components\TextInput::make('pickup_lng')
                    ->numeric()
                    ->readOnly(),
                Forms\Components\Select::make('dropoff_map_point')
                    ->label('Dropoff Map Point')
                    ->options(fn (): array => collect(config('ride.map_points', []))
                        ->mapWithKeys(fn (array $point, string $key): array => [$key => $point['label'] ?? $key])
                        ->all())
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->afterStateUpdated(function ($state, callable $set): void {
                        $point = config('ride.map_points.' . (string) $state);

                        if (! is_array($point)) {
                            return;
                        }

                        $set('dropoff_address', $point['label'] ?? null);
                        $set('dropoff_lat', $point['lat'] ?? null);
                        $set('dropoff_lng', $point['lng'] ?? null);
                    }),
                Forms\Components\View::make('filament.forms.components.location-map-picker')
                    ->viewData([
                        'label' => 'Dropoff Location Map',
                        'latField' => 'dropoff_lat',
                        'lngField' => 'dropoff_lng',
                        'addressField' => 'dropoff_address',
                    ])
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('dropoff_address')
                    ->maxLength(255),
                Forms\Components\TextInput::make('dropoff_lat')
                    ->numeric()
                    ->readOnly(),
                Forms\Components\TextInput::make('dropoff_lng')
                    ->numeric()
                    ->readOnly(),
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
                            return response()->streamDownload(fn () => print($pdf), "booking-receipt-{$record->id}.pdf");
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
