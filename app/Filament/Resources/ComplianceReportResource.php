<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\ComplianceReportResource\Pages;
use App\Models\ComplianceReport;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ComplianceReportResource extends Resource
{
    protected static ?string $model          = ComplianceReport::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'AI & Analytics';
    protected static ?string $label          = 'Compliance Report';
    protected static ?int    $navigationSort = 12;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user?->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ACCOUNTANT->value,
        ]) ?? false;
    }

    // Read-only resource — reports are created via ComplianceDashboard page
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('report_type')
                    ->label('Type')
                    ->formatStateUsing(fn ($state) => ComplianceReport::TYPES[$state] ?? $state),
                TextColumn::make('format')->label('Format')->badge(),
                TextColumn::make('status')->label('Status')->badge()
                    ->color(fn ($state) => match ($state) {
                        'ready'   => 'success',
                        'failed'  => 'danger',
                        'pending' => 'warning',
                        default   => 'gray',
                    }),
                TextColumn::make('period_from')->label('Period From')->date(),
                TextColumn::make('period_to')->label('Period To')->date(),
                TextColumn::make('created_at')->label('Requested At')->dateTime()->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => 'Pending', 'ready' => 'Ready', 'failed' => 'Failed']),
                Tables\Filters\SelectFilter::make('report_type')
                    ->options(ComplianceReport::TYPES),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (ComplianceReport $record) => $record->isReady())
                    ->action(function (ComplianceReport $record) {
                        if (! $record->hasFile()) {
                            return;
                        }
                        return Storage::disk('local')->download($record->file_path);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComplianceReports::route('/'),
        ];
    }
}
