<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\FraudFlagResource\Pages;
use App\Models\FraudFlag;
use App\Services\FraudDetectionService;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;

class FraudFlagResource extends Resource
{
    protected static ?string $model = FraudFlag::class;

    protected static ?string $navigationIcon  = 'heroicon-o-shield-exclamation';
    protected static ?string $navigationLabel = 'Fraud Monitoring';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int    $navigationSort  = 20;

    // -----------------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------------

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ACCOUNTANT]);
    }

    public static function canCreate(): bool        { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }

    // -----------------------------------------------------------------------
    // Table
    // -----------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        if (! Schema::hasTable('fraud_flags')) {
            return $table->columns([])->emptyStateHeading('Run migrations to view fraud flags.');
        }

        return $table
            ->query(FraudFlag::query()->latest())
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('entity_type')
                    ->label('Entity')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'driver'      => 'info',
                        'passenger'   => 'warning',
                        'transaction' => 'danger',
                        default       => 'gray',
                    }),

                TextColumn::make('entity_id')
                    ->label('Entity ID')
                    ->sortable(),

                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(60)
                    ->formatStateUsing(fn (?string $state) => $state !== null ? mb_scrub($state) : null)
                    ->tooltip(fn ($record) => $record->reason !== null ? mb_scrub($record->reason) : null)
                    ->searchable(),

                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'high'   => 'danger',
                        'medium' => 'warning',
                        'low'    => 'info',
                        default  => 'gray',
                    }),

                IconColumn::make('resolved')
                    ->label('Resolved')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('resolver.name')
                    ->label('Resolved By')
                    ->default('N/A'),

                TextColumn::make('resolved_at')
                    ->label('Resolved At')
                    ->formatStateUsing(static function ($state): string {
                        if (blank($state)) {
                            return 'N/A';
                        }

                        return \Illuminate\Support\Carbon::parse($state)->format('Y-m-d H:i');
                    }),

                TextColumn::make('created_at')
                    ->label('Flagged At')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->options([
                        'high'   => 'High',
                        'medium' => 'Medium',
                        'low'    => 'Low',
                    ]),

                SelectFilter::make('entity_type')
                    ->options([
                        'driver'      => 'Driver',
                        'passenger'   => 'Passenger',
                        'transaction' => 'Transaction',
                    ]),

                Filter::make('unresolved')
                    ->label('Unresolved Only')
                    ->query(fn (Builder $q) => $q->where('resolved', false))
                    ->default(),

                Filter::make('high_severity')
                    ->label('High Severity Only')
                    ->query(fn (Builder $q) => $q->where('severity', 'high')),
            ])
            ->actions([
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Resolve Fraud Flag')
                    ->modalDescription('Are you sure you want to mark this fraud flag as resolved? This will unblock any pending payouts for this entity.')
                    ->visible(fn ($record) => ! $record->resolved && auth()->user()?->role === UserRole::SUPER_ADMIN)
                    ->action(function (FraudFlag $record): void {
                        app(FraudDetectionService::class)->resolve($record, (int) auth()->id());

                        Notification::make()
                            ->title('Fraud flag resolved')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulk_resolve')
                    ->label('Resolve Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn () => auth()->user()?->role === UserRole::SUPER_ADMIN)
                    ->action(function (Collection $records): void {
                        $service = app(FraudDetectionService::class);
                        $userId  = (int) auth()->id();

                        $records->where('resolved', false)->each(
                            fn (FraudFlag $flag) => $service->resolve($flag, $userId)
                        );

                        Notification::make()
                            ->title('Selected flags resolved')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFraudFlags::route('/'),
        ];
    }
}
