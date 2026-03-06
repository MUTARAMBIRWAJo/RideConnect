<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $pluralModelLabel = 'Users';

    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::$model::where('is_approved', false)->count() ?: null;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Personal Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->maxLength(20),
                    ])->columns(3),
                
                Forms\Components\Section::make('Authentication')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->required()
                            ->maxLength(255)
                            ->visible(fn (Page $livewire): bool => $livewire instanceof Pages\CreateUser),
                        Forms\Components\Select::make('role')
                            ->options(collect(UserRole::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Access Control')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->label('Assigned Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->disabled(function (?User $record): bool {
                                $actor = auth()->user();

                                if (!$actor || !$record) {
                                    return false;
                                }

                                $actorIsAdmin = method_exists($actor, 'hasRole')
                                    ? $actor->hasRole('Admin')
                                    : (($actor->role?->value ?? $actor->role) === UserRole::ADMIN->value);

                                $recordIsSuperAdmin = method_exists($record, 'hasRole')
                                    ? $record->hasRole('Super_admin')
                                    : (($record->role?->value ?? $record->role) === UserRole::SUPER_ADMIN->value);

                                return $actorIsAdmin && $recordIsSuperAdmin;
                            }),
                    ]),
                
                Forms\Components\Section::make('Approval Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_approved')
                            ->label('Approved')
                            ->default(false),
                        Forms\Components\Toggle::make('is_verified')
                            ->label('Verified')
                            ->default(false),
                        Forms\Components\DateTimePicker::make('approved_at'),
                        Forms\Components\Select::make('approved_by')
                            ->relationship('approver', 'name')
                            ->label('Approved By'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('profile_photo')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=' . urlencode('User') . '&background=random'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-envelope'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'SUPER_ADMIN' => 'danger',
                        'ADMIN' => 'warning',
                        'ACCOUNTANT' => 'info',
                        'OFFICER' => 'purple',
                        'DRIVER' => 'success',
                        'PASSENGER' => 'cyan',
                    }),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Assigned Roles')
                    ->badge()
                    ->separator(',')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('is_approved')
                    ->boolean()
                    ->label('Approved')
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                Tables\Columns\IconColumn::make('is_verified')
                    ->boolean()
                    ->label('Verified')
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-question-mark-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options(collect(UserRole::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()]))
                    ->label('Role'),
                Tables\Filters\SelectFilter::make('is_approved')
                    ->options([
                        true => 'Approved',
                        false => 'Pending',
                    ])
                    ->label('Approval Status'),
                Tables\Filters\Filter::make('pending_approvals')
                    ->label('Pending Approval')
                    ->query(fn (Builder $query): Builder => $query->where('is_approved', false)),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (User $record): bool => static::canEdit($record)),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->visible(fn (User $record): bool => !$record->is_approved)
                        ->requiresConfirmation()
                        ->action(function (User $record) {
                            $record->update([
                                'is_approved' => true,
                                'approved_at' => now(),
                                'approved_by' => auth()->id(),
                            ]);
                        }),
                    Tables\Actions\Action::make('reject')
                        ->label('Reject')
                        ->icon('heroicon-o-x')
                        ->color('danger')
                        ->visible(fn (User $record): bool => !$record->is_approved)
                        ->requiresConfirmation()
                        ->action(function (User $record) {
                            $record->update([
                                'is_approved' => false,
                            ]);
                        }),
                ])
                    ->visible(fn (User $record): bool => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn ($records) => $records->each(fn ($record) => $record->update([
                            'is_approved' => true,
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ])))
                        ->visible(fn (): bool => auth()->user()?->can('edit users') ?? false),
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->can('delete users') ?? false),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => static::canCreate()),
            ]);
    }

    public static function getTabs(): array
    {
        return [
            'all' => Tab::make('All Users')
                ->icon('heroicon-o-user-group'),
            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_approved', false)),
            'approved' => Tab::make('Approved')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('is_approved', true)),
            'drivers' => Tab::make('Drivers')
                ->icon('heroicon-o-truck')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', UserRole::DRIVER->value)),
            'passengers' => Tab::make('Passengers')
                ->icon('heroicon-o-user')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', UserRole::PASSENGER->value)),
            'managers' => Tab::make('Managers')
                ->icon('heroicon-o-shield-check')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('role', UserRole::managerRoles())),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('view users') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('create users') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if (!$user || !$user->can('edit users')) {
            return false;
        }

        $actorIsAdmin = method_exists($user, 'hasRole')
            ? $user->hasRole('Admin')
            : (($user->role?->value ?? $user->role) === UserRole::ADMIN->value);

        $recordIsSuperAdmin = method_exists($record, 'hasRole')
            ? $record->hasRole('Super_admin')
            : (($record->role?->value ?? $record->role) === UserRole::SUPER_ADMIN->value);

        if ($actorIsAdmin && $recordIsSuperAdmin) {
            return false;
        }

        return true;
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        if (!$user || !$user->can('delete users')) {
            return false;
        }

        $actorIsAdmin = method_exists($user, 'hasRole')
            ? $user->hasRole('Admin')
            : (($user->role?->value ?? $user->role) === UserRole::ADMIN->value);

        $recordIsSuperAdmin = method_exists($record, 'hasRole')
            ? $record->hasRole('Super_admin')
            : (($record->role?->value ?? $record->role) === UserRole::SUPER_ADMIN->value);

        if ($actorIsAdmin && $recordIsSuperAdmin) {
            return false;
        }

        return true;
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
