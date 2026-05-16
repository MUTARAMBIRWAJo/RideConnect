<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\Ride;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RideManagement extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationLabel = 'Ride Management';

    protected static ?string $navigationGroup = 'Live Operations';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.ride-management';

    protected static ?string $slug = 'ride-management';

    public static function canAccess(): bool
    {
        return (auth()->user()?->role?->value ?? auth()->user()?->role) === UserRole::SUPER_ADMIN->value;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Ride::query()->with('driver.user'))
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Ride #')->sortable(),
                Tables\Columns\TextColumn::make('driver.user.name')->label('Driver')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('origin_address')->label('Origin')->limit(30)->searchable(),
                Tables\Columns\TextColumn::make('destination_address')->label('Destination')->limit(30)->searchable(),
                Tables\Columns\TextColumn::make('status')->badge()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->since()->label('Created')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'ACTIVE',
                        'completed' => 'COMPLETED',
                        'cancelled' => 'CANCELLED',
                        'in_progress' => 'IN PROGRESS',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = (string) ($data['value'] ?? '');

                        if ($value === '') {
                            return $query;
                        }

                        return $query->whereRaw('LOWER(status) = ?', [strtolower($value)]);
                    }),
                Tables\Filters\Filter::make('today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', now()->toDateString())),
                Tables\Filters\Filter::make('kigali')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $location): void {
                        $location->where('origin_address', 'ilike', '%kigali%')
                            ->orWhere('destination_address', 'ilike', '%kigali%');
                    })),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Ride details')
                    ->modalContent(fn (Ride $record) => view('filament.pages.partials.ride-detail', ['ride' => $record])),

                Tables\Actions\Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Ride $record): bool => ! in_array(strtolower((string) $record->status), ['cancelled', 'completed'], true))
                    ->action(function (Ride $record): void {
                        $record->update(['status' => 'CANCELLED']);

                        Notification::make()->title('Ride cancelled')->success()->send();
                    }),

                Tables\Actions\Action::make('force_complete')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Ride $record): bool => strtolower((string) $record->status) !== 'completed')
                    ->action(function (Ride $record): void {
                        $record->update(['status' => 'COMPLETED']);

                        Notification::make()->title('Ride force-completed')->success()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
