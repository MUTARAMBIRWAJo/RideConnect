<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Jobs\GenerateComplianceReportJob;
use App\Models\ComplianceReport;
use App\Modules\Compliance\DTOs\ComplianceReportDTO;
use App\Modules\Compliance\Services\ComplianceReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ComplianceDashboard extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Compliance';
    protected static ?string $navigationGroup = 'AI & Analytics';
    protected static ?string $title           = 'Compliance & Regulatory Reports';
    protected static ?int    $navigationSort  = 11;
    protected static string  $view            = 'filament.pages.compliance-dashboard';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole([
            UserRole::SUPER_ADMIN->value,
            UserRole::ACCOUNTANT->value,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_report')
                ->label('Generate Report')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->form([
                    Select::make('report_type')
                        ->label('Report Type')
                        ->options(ComplianceReport::TYPES)
                        ->required(),
                    Select::make('format')
                        ->label('Format')
                        ->options(['csv' => 'CSV', 'json' => 'JSON', 'pdf' => 'PDF'])
                        ->default('csv')
                        ->required(),
                    DatePicker::make('period_from')
                        ->label('From')
                        ->required(),
                    DatePicker::make('period_to')
                        ->label('To')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var ComplianceReportService $svc */
                    $svc    = app(ComplianceReportService::class);
                    $dto    = new ComplianceReportDTO(
                        reportType:  $data['report_type'],
                        format:      $data['format'],
                        periodFrom:  $data['period_from'],
                        periodTo:    $data['period_to'],
                        requestedBy: auth()->id(),
                    );
                    $report = $svc->request($dto);

                    GenerateComplianceReportJob::dispatch($report->id);

                    Notification::make()
                        ->title('Report queued')
                        ->body('Report generation is in progress. Refresh in a moment.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ComplianceReport::query()->latest())
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
                TextColumn::make('period_from')->label('From')->date(),
                TextColumn::make('period_to')->label('To')->date(),
                TextColumn::make('created_at')->label('Requested')->dateTime()->sortable(),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn (ComplianceReport $report) => $report->isReady())
                    ->action(function (ComplianceReport $report) {
                        if (! $report->hasFile()) {
                            Notification::make()->danger()->title('File not found')->send();
                            return;
                        }

                        return Storage::disk('local')->download($report->file_path);
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginate(20);
    }
}
