<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\ActionGroup::make([
                Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn ($record): bool => !$record->is_approved)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'is_approved' => true,
                            'approved_at' => now(),
                            'approved_by' => auth()->id(),
                        ]);
                    }),
                Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x')
                    ->color('danger')
                    ->visible(fn ($record): bool => !$record->is_approved)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'is_approved' => false,
                        ]);
                    }),
            ]),
            Actions\DeleteAction::make(),
        ];
    }
}
