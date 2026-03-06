<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\PermissionResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Permissions';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Permission Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('guard_name')
                            ->default('web')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\Select::make('roles')
                            ->label('Assigned Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ])->columns(2),
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
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Assigned Roles')
                    ->badge()
                    ->separator(',')
                    ->limit(60),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (Permission $record): bool => static::canEdit($record)),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (Permission $record): bool => static::canDelete($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => static::canDelete(new Permission())),
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
            'index' => Pages\ListPermissions::route('/'),
            'create' => Pages\CreatePermission::route('/create'),
            'edit' => Pages\EditPermission::route('/{record}/edit'),
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
