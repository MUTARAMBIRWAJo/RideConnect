<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class RevenueWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Payments';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'md' => 1,
    ];

    public static function canView(): bool
    {
        return Schema::hasTable((new Payment())->getTable());
    }

    protected function getPollingInterval(): ?string
    {
        return '90s';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Payment #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('RWF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->toggleable()
                    ->toggledHiddenByDefault(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading('No payment data available');
    }

    protected function getTableQuery(): Builder
    {
        return Payment::query()->select([
            'id',
            'amount',
            'status',
            'payment_method',
            'created_at',
        ]);
    }
}