<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

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

    /**
     * Get the mobile user associated with this user (if any)
     */
    public function mobileUser()
    {
        return $this->belongsTo(MobileUser::class, 'mobile_user_id');
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
     * Get trips where user is passenger
     */
    public function tripsAsPassenger()
    {
        return $this->hasMany(Trip::class, 'passenger_id');
    }
}
