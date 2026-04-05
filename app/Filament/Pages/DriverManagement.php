<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\Driver;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DriverManagement extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Driver Management';

    protected static ?string $navigationGroup = 'Fleet & Drivers';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.driver-management';

    protected static ?string $slug = 'driver-management';

    public static function canAccess(): bool
    {
        return (auth()->user()?->role?->value ?? auth()->user()?->role) === UserRole::SUPER_ADMIN->value;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Driver::query()->with('user'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Driver #')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.phone')->label('Phone')->toggleable(),
                Tables\Columns\TextColumn::make('availability_status')->label('Availability')->badge(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('rating')->label('Rating')->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('total_rides')->label('Trips Completed')->sortable(),
                Tables\Columns\TextColumn::make('approved_at')->label('Approved')->since()->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('availability_status')
                    ->label('Driver Status')
                    ->options([
                        'online' => 'Active / Online',
                        'offline' => 'Offline',
                        'suspended' => 'Suspended',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = (string) ($data['value'] ?? '');

                        if ($value === '') {
                            return $query;
                        }

                        if ($value === 'suspended') {
                            return $query->whereRaw('LOWER(status) = ?', ['suspended']);
                        }

                        return $query->whereRaw('LOWER(availability_status) = ?', [strtolower($value)]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Driver $record): bool => $record->approved_at === null)
                    ->action(function (Driver $record): void {
                        $record->update([
                            'approved_at' => now(),
                            'status' => 'ACTIVE',
                        ]);

                        Notification::make()->title('Driver approved')->success()->send();
                    }),

                Tables\Actions\Action::make('suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Driver $record): bool => strtolower((string) $record->status) !== 'suspended')
                    ->action(function (Driver $record): void {
                        $record->update([
                            'status' => 'SUSPENDED',
                            'availability_status' => 'OFFLINE',
                        ]);

                        Notification::make()->title('Driver suspended')->success()->send();
                    }),
            ])
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }
}
