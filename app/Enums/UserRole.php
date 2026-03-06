<?php

namespace App\Enums;

/**
 * User roles for role-based access control
 * 
 * Role Hierarchy:
 * - SUPER_ADMIN: Can view all data from the User table
 * - ADMIN/ACCOUNTANT/OFFICER (Managers): Can see their own data AND Mobile Users data
 * - Mobile Users (Drivers/Passengers): Can only see their own data
 */
enum UserRole: string
{
    // Manager roles
    case SUPER_ADMIN = 'SUPER_ADMIN';
    case ADMIN = 'ADMIN';
    case ACCOUNTANT = 'ACCOUNTANT';
    case OFFICER = 'OFFICER';
    
    // Mobile user roles
    case DRIVER = 'DRIVER';
    case PASSENGER = 'PASSENGER';

    /**
     * Check if the role is a manager role (Admin, Accountant, Officer)
     */
    public function isManager(): bool
    {
        return in_array($this, [
            self::SUPER_ADMIN,
            self::ADMIN,
            self::ACCOUNTANT,
            self::OFFICER,
        ]);
    }

    /**
     * Check if the role is a mobile user (Driver or Passenger)
     */
    public function isMobileUser(): bool
    {
        return in_array($this, [
            self::DRIVER,
            self::PASSENGER,
        ]);
    }

    /**
     * Check if the role is a Super Admin
     */
    public function isSuperAdmin(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    /**
     * Check if the role can view all users (Super Admin)
     */
    public function canViewAllUsers(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    /**
     * Check if the role can view mobile users (Managers)
     */
    public function canViewMobileUsers(): bool
    {
        return $this->isManager();
    }

    /**
     * Check if the role can only see their own data
     */
    public function canOnlySeeOwnData(): bool
    {
        return $this->isMobileUser();
    }

    /**
     * Get display label for the role
     */
    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::ACCOUNTANT => 'Accountant',
            self::OFFICER => 'Officer',
            self::DRIVER => 'Driver',
            self::PASSENGER => 'Passenger',
        };
    }

    /**
     * Get all manager roles
     */
    public static function managerRoles(): array
    {
        return [
            self::SUPER_ADMIN->value,
            self::ADMIN->value,
            self::ACCOUNTANT->value,
            self::OFFICER->value,
        ];
    }

    /**
     * Get all mobile user roles
     */
    public static function mobileUserRoles(): array
    {
        return [
            self::DRIVER->value,
            self::PASSENGER->value,
        ];
    }

    /**
     * Get all roles
     */
    public static function allRoles(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}
