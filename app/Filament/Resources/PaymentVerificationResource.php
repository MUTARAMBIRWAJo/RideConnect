<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentVerificationResource\Pages;
use App\Models\PaymentSubmission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;

class PaymentVerificationResource extends Resource
{
    protected static ?string $model = PaymentSubmission::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Payment Verification';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Payment Details')
                ->schema([
                    Forms\Components\TextInput::make('amount')
                        ->prefix('RWF')
                        ->disabled(),
                    Forms\Components\TextInput::make('payer_phone')
                        ->tel()
                        ->disabled(),
                    Forms\Components\TextInput::make('transaction_reference')
                        ->disabled(),
                    Forms\Components\FileUpload::make('screenshot_path')
                        ->image()
                        ->directory('payment-screenshots')
                        ->disabled(),
                ])
                ->columns(2),
            
            Forms\Components\Section::make('Verification')
                ->schema([
                    Forms\Components\Select::make('verification_status')
                        ->options([
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                        ])
                        ->required(),
                    Forms\Components\Textarea::make('notes')
                        ->label('Verification Notes')
                        ->rows(3),
                ])
                ->visible(fn ($record) => $record && $record->verification_status === 'pending'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Passenger')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('payment.id')
                    ->label('Payment ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('trip.id')
                    ->label('Trip ID')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('RWF')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payer_phone')
                    ->label('Phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('transaction_reference')
                    ->label('Transaction ID')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('verification_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\ImageColumn::make('screenshot_path')
                    ->label('Screenshot')
                    ->circular()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('verifiedBy.name')
                    ->label('Verified By')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('verified_at')
                    ->dateTime()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('verification_status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Action::make('approve')
                    ->label('Approve')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('notes')
                            ->label('Approval Notes (Optional)')
                            ->rows(2),
                    ])
                    ->action(function (PaymentSubmission $record, array $data) {
                        $record->approve(auth()->id(), $data['notes'] ?? null);
                    })
                    ->visible(fn (PaymentSubmission $record) => $record->verification_status === 'pending'),
                Action::make('reject')
                    ->label('Reject')
                    ->color('danger')
                    ->icon('heroicon-o-x-mark')
                    ->requiresConfirmation()
                    ->form([
                        Textarea::make('notes')
                            ->label('Rejection Reason')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (PaymentSubmission $record, array $data) {
                        $record->reject(auth()->id(), $data['notes']);
                    })
                    ->visible(fn (PaymentSubmission $record) => $record->verification_status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentVerifications::route('/'),
            'view' => Pages\ViewPaymentVerification::route('/{record}'),
        ];
    }
}
