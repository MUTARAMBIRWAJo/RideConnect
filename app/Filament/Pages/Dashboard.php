<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use Filament\Facades\Filament;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards;

    protected static ?string $navigationIcon = null;

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $panel = Filament::getCurrentPanel();
        $user = auth()->user();

        if (!$panel || !$user) {
            abort(403);
        }

        $panelId = $panel->getId();
        $roleValue = static::resolveUserRoleValue($user);

        $targetRoute = match ($roleValue) {
            UserRole::SUPER_ADMIN->value => "filament.{$panelId}.pages.super-dashboard",
            UserRole::ADMIN->value => "filament.{$panelId}.pages.admin-dashboard",
            UserRole::ACCOUNTANT->value => "filament.{$panelId}.pages.accountant-dashboard",
            UserRole::OFFICER->value => "filament.{$panelId}.pages.officer-dashboard",
            default => null,
        };

        if (!$targetRoute) {
            abort(403);
        }

        $this->redirectRoute($targetRoute, navigate: true);
    }
}
