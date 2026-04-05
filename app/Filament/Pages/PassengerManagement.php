<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\MobileUser;
use App\Models\Trip;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class PassengerManagement extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Passenger Management';

    protected static ?string $navigationGroup = 'Passengers';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.passenger-management';

    protected static ?string $slug = 'passenger-management';

    public static function canAccess(): bool
    {
        return (auth()->user()?->role?->value ?? auth()->user()?->role) === UserRole::SUPER_ADMIN->value;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(MobileUser::query()->where('role', UserRole::PASSENGER->value))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Passenger #')->sortable(),
                Tables\Columns\TextColumn::make('full_name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('is_verified')
                    ->label('Verified')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'YES' : 'NO')
                    ->color(fn (bool $state): string => $state ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('created_at')->since()->label('Joined'),
                Tables\Columns\TextColumn::make('ride_history')
                    ->label('Ride History')
                    ->state(function (MobileUser $record): string {
                        $count = Trip::query()->where('passenger_id', $record->id)->count();

                        return number_format($count) . ' trips';
                    }),
                Tables\Columns\TextColumn::make('risk_flag')
                    ->label('Suspicious Behaviour')
                    ->state(function (MobileUser $record): string {
                        $cancelled = Trip::query()
                            ->where('passenger_id', $record->id)
                            ->whereIn('status', ['CANCELLED', 'cancelled'])
                            ->count();

                        return $cancelled >= 5 ? 'Review' : 'Normal';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Review' ? 'danger' : 'success'),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }
}
