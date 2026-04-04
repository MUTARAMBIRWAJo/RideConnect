<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Support\Htmlable;

class SuperDashboard extends BaseDashboard
{
    protected static string $routePath = '/super-dashboard';

    protected static string $view = 'filament.pages.super-dashboard';

    protected static function dashboardRole(): UserRole
    {
        return UserRole::SUPER_ADMIN;
    }

    public static function getNavigationLabel(): string
    {
        return 'Super Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-shield-check';
    }

    protected function getHeaderWidgets(): array
    {
        return $this->getVisibleWidgets();
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
