<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected array $permissionsToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->permissionsToSync = $data['permissions'] ?? [];
        unset($data['permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Role $role */
        $role = $this->record;
        $role->syncPermissions($this->permissionsToSync);
    }
}
