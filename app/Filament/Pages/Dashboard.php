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

        if (! $user) {
            abort(403);
        }

        $panelId = $panel?->getId() ?? 'admin';
        $roleValue = static::resolveUserRoleValue($user);

        // Fallback for accounts managed only through Spatie roles.
        if (! $roleValue) {
            $roleValue = match (true) {
                static::userHasRole($user, 'Super_admin', UserRole::SUPER_ADMIN) => UserRole::SUPER_ADMIN->value,
                static::userHasRole($user, 'Admin', UserRole::ADMIN) => UserRole::ADMIN->value,
                static::userHasRole($user, 'Accountant', UserRole::ACCOUNTANT) => UserRole::ACCOUNTANT->value,
                static::userHasRole($user, 'Officer', UserRole::OFFICER) => UserRole::OFFICER->value,
                static::userHasRole($user, 'Driver', UserRole::DRIVER) => UserRole::DRIVER->value,
                static::userHasRole($user, 'Passenger', UserRole::PASSENGER) => UserRole::PASSENGER->value,
                default => null,
            };
        }

        $targetRoute = match ($roleValue) {
            UserRole::SUPER_ADMIN->value => "filament.{$panelId}.pages.super-dashboard",
            UserRole::ADMIN->value => "filament.{$panelId}.pages.admin-dashboard",
            UserRole::ACCOUNTANT->value => "filament.{$panelId}.pages.accountant-dashboard",
            UserRole::OFFICER->value => "filament.{$panelId}.pages.officer-dashboard-v2",
            UserRole::DRIVER->value => "filament.{$panelId}.pages.driver-dashboard",
            UserRole::PASSENGER->value => "filament.{$panelId}.pages.passenger-dashboard",
            default => null,
        };

        if (! $targetRoute) {
            abort(403);
        }

        $this->redirectRoute($targetRoute, navigate: true);
    }
}
