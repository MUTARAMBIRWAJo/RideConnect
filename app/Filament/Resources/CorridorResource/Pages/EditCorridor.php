<?php

namespace App\Filament\Resources\CorridorResource\Pages;

use App\Filament\Resources\CorridorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCorridor extends EditRecord
{
    protected static string $resource = CorridorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
