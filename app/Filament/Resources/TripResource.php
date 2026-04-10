<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\TripResource\Pages;
use App\Models\Booking;
use App\Models\Ride;
use App\Models\MobileUser;
use App\Models\User;
use App\Models\Trip;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
                        Forms\Components\Select::make('ride_id')
                            ->relationship(
                                name: 'ride',
                                titleAttribute: 'id',
                                modifyQueryUsing: fn (EloquentBuilder $query): EloquentBuilder => $query->orderByDesc('id')
                            )
                            ->getOptionLabelFromRecordUsing(fn (Ride $record): string => sprintf(
                                '#%d | %s -> %s',
                                $record->id,
                                $record->origin_address ?? 'Unknown',
                                $record->destination_address ?? 'Unknown'
                            ))
                            ->searchable()
                            ->preload()
                            ->live(),
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
                                $record->dropoff_address ? ('-> ' . $record->dropoff_address) : '',
                                $record->status ?? 'PENDING'
                            ))
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('passenger_id')
                            ->label('Passenger')
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search): array {
                                return MobileUser::query()
                                    ->where(function (Builder $query) use ($search): void {
                                        $query->where('first_name', 'like', "%{$search}%")
                                            ->orWhere('last_name', 'like', "%{$search}%")
                                            ->orWhere('email', 'like', "%{$search}%")
                                            ->orWhere('phone', 'like', "%{$search}%");
                                    })
                                    ->orderBy('first_name')
                                    ->limit(20)
                                    ->get()
                                    ->mapWithKeys(fn (MobileUser $record): array => [
                                        $record->id => trim($record->full_name . ' | ' . $record->phone),
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
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $name = trim((string) ($data['name'] ?? ''));
                                $email = trim((string) ($data['email'] ?? ''));
                                $phone = trim((string) ($data['phone'] ?? ''));

                                $mobileUser = MobileUser::query()->updateOrCreate(
                                    ['email' => $email],
                                    [
                                        'first_name' => Str::of($name)->before(' ')->trim()->value() ?: $name,
                                        'last_name' => Str::of($name)->after(' ')->trim()->value() ?: 'Passenger',
                                        'phone' => $phone,
                                        'role' => UserRole::PASSENGER,
                                        'is_verified' => true,
                                    ]
                                );

                                User::query()->updateOrCreate(
                                    ['email' => $email],
                                    [
                                        'name' => $name,
                                        'phone' => $phone,
                                        'password' => Hash::make(Str::random(16)),
                                        'role' => UserRole::PASSENGER,
                                        'mobile_user_id' => $mobileUser->id,
                                        'is_verified' => true,
                                        'is_approved' => true,
                                        'approved_at' => now(),
                                    ]
                                );

                                return (int) $mobileUser->id;
                            })
                            ->preload(),
                        Forms\Components\Select::make('driver_id')
                            ->relationship('driver', 'id')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('pickup_map_point')
                            ->label('Pickup Map Point')
                            ->options(fn (): array => collect(config('ride.map_points', []))
                                ->mapWithKeys(fn (array $point, string $key): array => [$key => $point['label'] ?? $key])
                                ->all())
                            ->searchable()
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $point = config('ride.map_points.' . (string) $state);

                                if (! is_array($point)) {
                                    return;
                                }

                                $set('pickup_location', $point['label'] ?? null);
                                $set('pickup_lat', $point['lat'] ?? null);
                                $set('pickup_lng', $point['lng'] ?? null);
                            }),
                        Forms\Components\View::make('filament.forms.components.location-map-picker')
                            ->viewData([
                                'label' => 'Pickup Location Map',
                                'latField' => 'pickup_lat',
                                'lngField' => 'pickup_lng',
                                'addressField' => 'pickup_location',
                            ])
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('pickup_location')
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
                            ->dehydrated(false)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $point = config('ride.map_points.' . (string) $state);

                                if (! is_array($point)) {
                                    return;
                                }

                                $set('dropoff_location', $point['label'] ?? null);
                                $set('dropoff_lat', $point['lat'] ?? null);
                                $set('dropoff_lng', $point['lng'] ?? null);
                            }),
                        Forms\Components\View::make('filament.forms.components.location-map-picker')
                            ->viewData([
                                'label' => 'Dropoff Location Map',
                                'latField' => 'dropoff_lat',
                                'lngField' => 'dropoff_lng',
                                'addressField' => 'dropoff_location',
                            ])
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('dropoff_location')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('dropoff_lat')
                            ->numeric()
                            ->readOnly(),
                        Forms\Components\TextInput::make('dropoff_lng')
                            ->numeric()
                            ->readOnly(),
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
