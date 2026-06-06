<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'mobile_user_id',
        'manager_id',
        'phone',
        'profile_photo',
        'is_verified',
        'is_approved',
        'approved_by',
        'approved_at',
        'google_id',
        'two_factor_enabled',
        'password_reset_token',
        'password_reset_expires_at',
        'two_factor_secret',
        'two_factor_confirmed_at',
        'two_factor_backup_codes',
        'mfa_attempts',
        'mfa_locked_until',
        'last_login_ip',
        'last_login_user_agent',
        'last_login_at',
        'preferred_payment_method',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_verified' => 'boolean',
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_backup_codes' => 'array',
            'last_login_at' => 'datetime',
            'mfa_locked_until' => 'datetime',
        ];
    }

    /**
     * The default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_approved' => false,
    ];

    /**
     * Check if the user is a Super Admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Check if the user is a Manager (Admin, Accountant, Officer)
     */
    public function isManager(): bool
    {
        return $this->role && $this->role->isManager();
    }

    /**
     * Check if the user is a Mobile User (Driver or Passenger)
     */
    public function isMobileUser(): bool
    {
        return $this->role && $this->role->isMobileUser();
    }

    /**
     * Check if the user is a Driver
     */
    public function isDriver(): bool
    {
        return $this->role === UserRole::DRIVER;
    }

    /**
     * Check if the user is a Passenger
     */
    public function isPassenger(): bool
    {
        return $this->role === UserRole::PASSENGER;
    }

    /**
     * Check if the user can view all users (Super Admin only)
     */
    public function canViewAllUsers(): bool
    {
        return $this->role && $this->role->canViewAllUsers();
    }

    /**
     * Check if the user can view mobile users (Managers)
     */
    public function canViewMobileUsers(): bool
    {
        return $this->role && $this->role->canViewMobileUsers();
    }

    /**
     * Check if the user can only see their own data
     */
    public function canOnlySeeOwnData(): bool
    {
        return $this->role && $this->role->canOnlySeeOwnData();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->role && $this->role->isManager()) {
            return true;
        }

        if (! $this->is_approved) {
            return false;
        }

        return $this->role instanceof UserRole;
    }

    /**
     * Get the mobile user associated with this user (if any)
     */
    public function mobileUser()
    {
        return $this->belongsTo(MobileUser::class, 'mobile_user_id');
    }

    /**
     * Get the driver profile associated with this user.
     */
    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * Get the manager associated with this user (if any)
     */
    public function manager()
    {
        return $this->belongsTo(Manager::class, 'manager_id');
    }

    /**
     * Get the user who approved this user (if any)
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the bookings for this user (as passenger)
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * Get saved locations for this user (if SavedLocation model exists)
     */
    public function savedLocations()
    {
        // Return empty collection if SavedLocation model doesn't exist
        if (! class_exists('App\Models\SavedLocation')) {
            return collect();
        }

        return $this->hasMany('App\Models\SavedLocation');
    }

    /**
     * Get trips where user is passenger
     */
    public function tripsAsPassenger()
    {
        return $this->hasMany(Trip::class, 'passenger_id');
    }

    /**
     * Get registered mobile push tokens for this user.
     */
    public function mobileDeviceTokens()
    {
        return $this->hasMany(MobileDeviceToken::class);
    }

    /**
     * Check if user has MFA enabled
     */
    public function hasMfaEnabled(): bool
    {
        return (bool) $this->two_factor_enabled;
    }

    /**
     * Check if MFA is confirmed
     */
    public function hasMfaConfirmed(): bool
    {
        return (bool) $this->two_factor_confirmed_at;
    }

    /**
     * Check if account is locked due to MFA brute force
     */
    public function isMfaLocked(): bool
    {
        if (! $this->mfa_locked_until) {
            return false;
        }

        return now()->lessThan($this->mfa_locked_until);
    }

    /**
     * Increment MFA attempts
     */
    public function incrementMfaAttempts(): void
    {
        $this->increment('mfa_attempts');

        if ($this->mfa_attempts >= 5) {
            $this->update([
                'mfa_locked_until' => now()->addMinutes(10),
                'mfa_attempts' => 5,
            ]);
        }
    }

    /**
     * Reset MFA attempts
     */
    public function resetMfaAttempts(): void
    {
        $this->update([
            'mfa_attempts' => 0,
            'mfa_locked_until' => null,
        ]);
    }
}
