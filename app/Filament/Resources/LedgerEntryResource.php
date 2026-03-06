<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\LedgerEntryResource\Pages;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class LedgerEntryResource extends Resource
{
    protected static ?string $model = LedgerEntry::class;

    protected static ?string $navigationIcon  = 'heroicon-o-book-open';
    protected static ?string $navigationLabel = 'Ledger Viewer';
    protected static ?string $navigationGroup = 'Finance';
    protected static ?int    $navigationSort  = 10;

    // -----------------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------------

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user && in_array($user->role, [UserRole::SUPER_ADMIN, UserRole::ACCOUNTANT]);
    }

    public static function canCreate(): bool   { return false; }
    public static function canEdit($record): bool   { return false; }
    public static function canDelete($record): bool { return false; }

    // -----------------------------------------------------------------------
    // Table
    // -----------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        if (! Schema::hasTable('ledger_entries')) {
            return $table->columns([])->emptyStateHeading('Run migrations to view ledger entries.');
        }

        return $table
            ->query(
                LedgerEntry::query()
                    ->with(['account', 'transaction'])
                    ->latest('created_at')
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Date / Time')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('transaction.uuid')
                    ->label('Transaction UUID')
                    ->limit(20)
                    ->formatStateUsing(fn (?string $state) => $state !== null ? mb_scrub($state) : null)
                    ->tooltip(fn ($record) => $record->transaction?->uuid !== null ? mb_scrub($record->transaction->uuid) : null)
                    ->searchable(),

                TextColumn::make('account.name')
                    ->label('Account')
                    ->formatStateUsing(fn (?string $state) => $state !== null ? mb_scrub($state) : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('account.type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'asset'     => 'success',
                        'liability' => 'warning',
                        'revenue'   => 'info',
                        'expense'   => 'danger',
                        default     => 'gray',
                    }),

                TextColumn::make('debit')
                    ->label('Debit (RWF)')
                    ->numeric(decimalPlaces: 2)
                    ->color('danger')
                    ->sortable(),

                TextColumn::make('credit')
                    ->label('Credit (RWF)')
                    ->numeric(decimalPlaces: 2)
                    ->color('success')
                    ->sortable(),

                TextColumn::make('reference_type')
                    ->label('Reference')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'payout'     => 'success',
                        'payment'    => 'info',
                        'refund'     => 'danger',
                        'webhook'    => 'warning',
                        'adjustment' => 'gray',
                        default      => 'gray',
                    }),

                TextColumn::make('reference_id')
                    ->label('Ref ID')
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->formatStateUsing(fn (?string $state) => $state !== null ? mb_scrub($state) : null)
                    ->tooltip(fn ($record) => $record->description !== null ? mb_scrub($record->description) : null),
            ])
            ->filters([
                SelectFilter::make('reference_type')
                    ->options([
                        'payment'    => 'Payment',
                        'payout'     => 'Payout',
                        'refund'     => 'Refund',
                        'adjustment' => 'Adjustment',
                        'webhook'    => 'Webhook',
                    ])
                    ->label('Reference Type'),

                SelectFilter::make('account_type')
                    ->label('Account Type')
                    ->options([
                        'asset'     => 'Asset',
                        'liability' => 'Liability',
                        'revenue'   => 'Revenue',
                        'expense'   => 'Expense',
                    ])
                    ->query(fn (Builder $query, array $data) =>
                        $data['value']
                            ? $query->whereHas('account', fn ($q) => $q->where('type', $data['value']))
                            : $query
                    ),

                Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('From'),
                        \Filament\Forms\Components\DatePicker::make('to')->label('To'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                        ->when($data['to'],   fn ($q) => $q->whereDate('created_at', '<=', $data['to']))
                    ),

                SelectFilter::make('driver_account')
                    ->label('Driver')
                    ->options(fn () => Schema::hasTable('ledger_accounts')
                        ? LedgerAccount::where('owner_type', 'driver')
                            ->get()
                            ->mapWithKeys(fn ($a) => [$a->id => "Driver #{$a->owner_id}"])
                            ->toArray()
                        : []
                    )
                    ->query(fn (Builder $query, array $data) =>
                        $data['value']
                            ? $query->where('account_id', $data['value'])
                            : $query
                    ),
            ])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100]);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLedgerEntries::route('/'),
        ];
    }
}
