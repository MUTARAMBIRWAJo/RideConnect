<?php

namespace App\Filament\Admin\Pages;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ManageUserMfa extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';
    protected static string $view = 'filament.admin.pages.manage-user-mfa';
    protected static ?string $navigationLabel = 'User MFA Settings';
    protected static ?string $title = 'Manage User Two-Factor Authentication';
    protected static ?int $navigationSort = 50;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                User::query()
                    ->whereIn('role', ['super_admin', 'admin', 'officer', 'accountant'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                    
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->copyable(),
                    
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'super_admin' => 'danger',
                        'admin' => 'warning',
                        'officer' => 'info',
                        'accountant' => 'success',
                        default => 'gray',
                    }),
                    
                Tables\Columns\IconColumn::make('two_factor_enabled')
                    ->label('MFA Enabled')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                    
                Tables\Columns\TextColumn::make('two_factor_confirmed_at')
                    ->label('MFA Since')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('Not enabled'),
                    
                Tables\Columns\TextColumn::make('mfa_attempts')
                    ->label('Failed Attempts')
                    ->badge()
                    ->color(fn($state) => $state > 3 ? 'danger' : ($state > 1 ? 'warning' : 'gray')),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('two_factor_enabled')
                    ->label('MFA Status')
                    ->placeholder('All users')
                    ->trueLabel('MFA Enabled')
                    ->falseLabel('MFA Disabled'),
                    
                Tables\Filters\SelectFilter::make('role')
                    ->label('User Role')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'admin' => 'Admin',
                        'officer' => 'Officer',
                        'accountant' => 'Accountant',
                    ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('reset_mfa_attempts')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->label('Reset Failed Attempts')
                        ->requiresConfirmation()
                        ->action(function (User $record) {
                            $record->update([
                                'mfa_attempts' => 0,
                                'mfa_locked_until' => null,
                            ]);
                            
                            $this->notify('success', "{$record->name}'s failed MFA attempts have been reset.");
                        })
                        ->hidden(fn(User $record) => $record->mfa_attempts === 0),
                        
                    Tables\Actions\Action::make('disable_mfa')
                        ->icon('heroicon-o-shield-exclamation')
                        ->label('Disable MFA')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Disable Two-Factor Authentication')
                        ->modalDescription('This user will need to set up MFA again on their next login.')
                        ->action(function (User $record) {
                            $record->update([
                                'two_factor_secret' => null,
                                'two_factor_confirmed_at' => null,
                                'two_factor_backup_codes' => null,
                                'two_factor_enabled' => false,
                                'mfa_attempts' => 0,
                                'mfa_locked_until' => null,
                            ]);
                            
                            \Log::warning('MFA disabled by admin', [
                                'admin_id' => auth()->id(),
                                'admin_name' => auth()->user()->name,
                                'user_id' => $record->id,
                                'user_name' => $record->name,
                            ]);
                            
                            $this->notify('success', "MFA has been disabled for {$record->name}.");
                        })
                        ->visible(fn(User $record) => $record->hasMfaEnabled()),
                        
                    Tables\Actions\Action::make('unlock_mfa')
                        ->icon('heroicon-o-lock-open')
                        ->label('Unlock MFA')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function (User $record) {
                            if ($record->isMfaLocked()) {
                                $record->resetMfaAttempts();
                                $this->notify('success', "MFA lockout has been cleared for {$record->name}.");
                            } else {
                                $this->notify('info', "This user is not currently locked out.");
                            }
                        })
                        ->visible(fn(User $record) => $record->isMfaLocked()),
                        
                    Tables\Actions\ViewAction::make()
                        ->form([
                            Forms\Components\Section::make('User Information')
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->disabled(),
                                    Forms\Components\TextInput::make('email')
                                        ->disabled(),
                                    Forms\Components\TextInput::make('role')
                                        ->disabled(),
                                ]),
                                
                            Forms\Components\Section::make('MFA Status')
                                ->schema([
                                    Forms\Components\TextInput::make('two_factor_enabled')
                                        ->label('MFA Enabled')
                                        ->formatStateUsing(fn($state) => $state ? 'Yes' : 'No')
                                        ->disabled(),
                                    Forms\Components\TextInput::make('two_factor_confirmed_at')
                                        ->label('Enabled Since')
                                        ->formatStateUsing(fn($state) => $state?->format('M d, Y H:i') ?? 'N/A')
                                        ->disabled(),
                                    Forms\Components\TextInput::make('mfa_attempts')
                                        ->label('Failed Attempts')
                                        ->disabled(),
                                    Forms\Components\TextInput::make('mfa_locked_until')
                                        ->label('Locked Until')
                                        ->formatStateUsing(fn($state) => $state?->format('M d, Y H:i') ?? 'Not locked')
                                        ->disabled(),
                                ]),
                                
                            Forms\Components\Section::make('Security Log')
                                ->schema([
                                    Forms\Components\TextInput::make('last_login_ip')
                                        ->label('Last Login IP')
                                        ->disabled(),
                                    Forms\Components\TextInput::make('last_login_at')
                                        ->label('Last Login')
                                        ->formatStateUsing(fn($state) => $state?->format('M d, Y H:i') ?? 'Never')
                                        ->disabled(),
                                ]),
                        ]),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('reset_mfa_attempts_bulk')
                        ->label('Reset Failed Attempts')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->action(function ($records) {
                            $records->each(function (User $user) {
                                $user->resetMfaAttempts();
                            });
                            
                            $this->notify('success', 'Failed MFA attempts have been reset for ' . count($records) . ' user(s).');
                        }),
                        
                    Tables\Actions\BulkAction::make('disable_mfa_bulk')
                        ->label('Disable MFA')
                        ->icon('heroicon-o-shield-exclamation')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Disable MFA for Multiple Users')
                        ->action(function ($records) {
                            $records->each(function (User $user) {
                                $user->update([
                                    'two_factor_secret' => null,
                                    'two_factor_confirmed_at' => null,
                                    'two_factor_backup_codes' => null,
                                    'two_factor_enabled' => false,
                                    'mfa_attempts' => 0,
                                    'mfa_locked_until' => null,
                                ]);
                                
                                \Log::warning('MFA disabled by admin (bulk)', [
                                    'admin_id' => auth()->id(),
                                    'user_id' => $user->id,
                                ]);
                            });
                            
                            $this->notify('success', 'MFA has been disabled for ' . count($records) . ' user(s).');
                        }),
                ]),
            ])
            ->defaultSort('name');
    }
}
