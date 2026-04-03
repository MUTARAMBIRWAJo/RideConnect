<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Support\RoleDashboardConfig;
use App\Models\User;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class SuperDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards, HasFiltersForm;

    protected static string $routePath = '/super-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Super Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-shield-check';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::userHasRole(auth()->user(), 'Super_admin', UserRole::SUPER_ADMIN);
    }

    public static function canAccess(): bool
    {
        return static::userHasRole(auth()->user(), 'Super_admin', UserRole::SUPER_ADMIN);
    }

    public static function canView(): bool
    {
        return static::userHasRole(auth()->user(), 'Super_admin', UserRole::SUPER_ADMIN);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getColumns(): int | string | array
    {
        return RoleDashboardConfig::columnsForRole(UserRole::SUPER_ADMIN->value);
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return RoleDashboardConfig::visibleWidgetsForRole(UserRole::SUPER_ADMIN->value);
    }

    public function canManageUsers(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $isSuperAdminByRoleEnum = ($user->role?->value ?? $user->role) === UserRole::SUPER_ADMIN->value;
        $isSuperAdminBySpatie = method_exists($user, 'hasRole')
            ? ($user->hasRole('Super_admin') || $user->hasRole('SUPER_ADMIN'))
            : false;

        return $isSuperAdminByRoleEnum || $isSuperAdminBySpatie || ($user->can('view users') ?? false);
    }

    /**
     * @return array<string, int>
     */
    public function getUserManagementStats(): array
    {
        return [
            'total' => (int) User::query()->count(),
            'pending' => (int) User::query()->where('is_approved', false)->count(),
            'managers' => (int) User::query()->whereIn('role', UserRole::managerRoles())->count(),
            'mobile' => (int) User::query()->whereIn('role', UserRole::mobileUserRoles())->count(),
        ];
    }
}
