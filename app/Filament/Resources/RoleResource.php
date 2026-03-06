<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Roles';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Role Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('guard_name')
                            ->default('web')
                            ->required()
                            ->maxLength(100),
                    ])->columns(2),

                Forms\Components\Section::make('Permissions')
                    ->schema([
                        Forms\Components\Select::make('permissions')
                            ->label('Assigned Permissions')
                            ->relationship('permissions', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('guard_name')
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('permissions.name')
                    ->label('Permissions')
                    ->badge()
                    ->separator(',')
                    ->limit(60),
                Tables\Columns\TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Assigned Users')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Role $record): bool => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Role $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => static::canDelete(new Role())),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => static::canCreate()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::isSuperAdmin(auth()->user());
    }

    public static function canCreate(): bool
    {
        return static::isSuperAdmin(auth()->user());
    }

    public static function canEdit(Model $record): bool
    {
        return static::isSuperAdmin(auth()->user());
    }

    public static function canDelete(Model $record): bool
    {
        return static::isSuperAdmin(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    private static function isSuperAdmin($user): bool
    {
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('Super_admin')) {
            return true;
        }

        return ($user->role?->value ?? $user->role) === UserRole::SUPER_ADMIN->value;
    }
}
