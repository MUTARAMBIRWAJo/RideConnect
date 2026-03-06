<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected array $permissionsToSync = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->permissionsToSync = $data['permissions'] ?? [];
        unset($data['permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var Role $role */
        $role = $this->record;
        $role->syncPermissions($this->permissionsToSync);
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn (): bool => RoleResource::canDelete($this->record)),
        ];
    }
}
