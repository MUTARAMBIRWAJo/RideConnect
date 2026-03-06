<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\DriverPayout;
use App\Services\AccountantPayoutService;
use App\Services\DriverEarningService;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DriverDailyEarnings extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Driver Daily Earnings';

    protected static ?string $navigationGroup = 'Passengers';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.driver-daily-earnings';

    public string $selectedDate;

    private array $incomeCache = [];

    private array $payoutCache = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->selectedDate = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return (auth()->user()?->role?->value ?? auth()->user()?->role) === UserRole::ACCOUNTANT->value;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Driver::query()->with('user'))
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Driver Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_income')
                    ->label('Total Income')
                    ->state(fn (Driver $record): string => 'RWF ' . number_format((float) $this->incomeFor($record)['total_driver_income'], 2))
                    ->sortable(false),
                Tables\Columns\TextColumn::make('commission')
                    ->label('Commission (8%)')
                    ->state(fn (Driver $record): string => 'RWF ' . number_format((float) $this->incomeFor($record)['commission'], 2)),
                Tables\Columns\TextColumn::make('net_payout')
                    ->label('Net Payout (92%)')
                    ->state(fn (Driver $record): string => 'RWF ' . number_format((float) $this->incomeFor($record)['payout_amount'], 2)),
                Tables\Columns\TextColumn::make('payout_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Driver $record): string => $this->payoutFor($record)?->status === 'processed' ? 'Paid' : 'Unpaid')
                    ->color(fn (string $state): string => $state === 'Paid' ? 'success' : 'warning'),
            ])
            ->filters([
                Tables\Filters\Filter::make('date')
                    ->form([
                        DatePicker::make('date')
                            ->default(now()->toDateString())
                            ->native(false),
                    ])
                    ->indicateUsing(function (array $data): ?string {
                        if (empty($data['date'])) {
                            return null;
                        }

                        return 'Date: ' . Carbon::parse($data['date'])->toDateString();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (! empty($data['date'])) {
                            $this->selectedDate = Carbon::parse($data['date'])->toDateString();
                            $this->incomeCache = [];
                            $this->payoutCache = [];
                        }

                        return $query;
                    }),
                Tables\Filters\SelectFilter::make('paid_status')
                    ->label('Paid/Unpaid')
                    ->options([
                        'paid' => 'Paid',
                        'unpaid' => 'Unpaid',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['value'] ?? null) === 'paid') {
                            return $query->whereHas('payouts', fn (Builder $payoutsQuery) => $payoutsQuery
                                ->whereDate('payout_date', $this->selectedDate)
                                ->where('status', 'processed'));
                        }

                        if (($data['value'] ?? null) === 'unpaid') {
                            return $query->whereDoesntHave('payouts', fn (Builder $payoutsQuery) => $payoutsQuery
                                ->whereDate('payout_date', $this->selectedDate)
                                ->where('status', 'processed'));
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('pay_now')
                    ->label('Send Earning')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Driver $record): bool => $this->payoutFor($record)?->status !== 'processed')
                    ->requiresConfirmation()
                    ->action(function (Driver $record): void {
                        try {
                            app(AccountantPayoutService::class)->processSingleDriverPayout(
                                $record->id,
                                $this->selectedDate,
                                auth()->id(),
                            );

                            $this->incomeCache = [];
                            $this->payoutCache = [];

                            Notification::make()
                                ->title('Payout processed successfully')
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Payout failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('send_selected_earnings')
                    ->label('Send Selected Earnings')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records): void {
                        $driverIds = $records
                            ->filter(fn (Driver $driver) => $this->payoutFor($driver)?->status !== 'processed')
                            ->pluck('id')
                            ->map(fn ($id) => (int) $id)
                            ->values()
                            ->all();

                        if (count($driverIds) === 0) {
                            Notification::make()
                                ->title('No unpaid drivers selected')
                                ->warning()
                                ->send();

                            return;
                        }

                        try {
                            $result = app(AccountantPayoutService::class)->processBulkPayout(
                                $driverIds,
                                $this->selectedDate,
                                auth()->id(),
                            );

                            $totalPayout = (float) $result->sum('payout_amount');

                            $this->incomeCache = [];
                            $this->payoutCache = [];

                            Notification::make()
                                ->title('Bulk payout processed')
                                ->body('Total payout sent: RWF ' . number_format($totalPayout, 2))
                                ->success()
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->title('Bulk payout failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('select_date')
                    ->label('Settlement Date')
                    ->icon('heroicon-o-calendar-days')
                    ->form([
                        DatePicker::make('date')
                            ->required()
                            ->default(fn (): string => $this->selectedDate)
                            ->native(false),
                    ])
                    ->action(function (array $data): void {
                        $this->selectedDate = Carbon::parse($data['date'])->toDateString();
                        $this->incomeCache = [];
                        $this->payoutCache = [];
                    }),
                Tables\Actions\Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(fn (): StreamedResponse => $this->exportCsv()),
            ]);
    }

    private function incomeFor(Driver $driver): array
    {
        $key = $driver->id . '|' . $this->selectedDate;

        if (! isset($this->incomeCache[$key])) {
            $this->incomeCache[$key] = app(DriverEarningService::class)
                ->calculateDriverDailyIncome($driver->id, $this->selectedDate);
        }

        return $this->incomeCache[$key];
    }

    private function payoutFor(Driver $driver): ?DriverPayout
    {
        $key = $driver->id . '|' . $this->selectedDate;

        if (! array_key_exists($key, $this->payoutCache)) {
            $this->payoutCache[$key] = DriverPayout::query()
                ->where('driver_id', $driver->id)
                ->whereDate('payout_date', $this->selectedDate)
                ->first();
        }

        return $this->payoutCache[$key];
    }

    private function exportCsv(): StreamedResponse
    {
        $date = $this->selectedDate;
        $rows = Driver::query()->with('user')->orderBy('id')->get()->map(function (Driver $driver) {
            $income = $this->incomeFor($driver);
            $status = $this->payoutFor($driver)?->status === 'processed' ? 'Paid' : 'Unpaid';

            return [
                'Driver Name' => $driver->user?->name,
                'Date' => $this->selectedDate,
                'Total Income' => number_format((float) $income['total_driver_income'], 2, '.', ''),
                'Commission (8%)' => number_format((float) $income['commission'], 2, '.', ''),
                'Net Payout (92%)' => number_format((float) $income['payout_amount'], 2, '.', ''),
                'Status' => $status,
            ];
        });

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Driver Name', 'Date', 'Total Income', 'Commission (8%)', 'Net Payout (92%)', 'Status']);

            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }

            fclose($handle);
        }, 'driver-daily-earnings-' . $date . '.csv', ['Content-Type' => 'text/csv']);
    }
}
