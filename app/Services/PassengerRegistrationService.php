<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PassengerRegistrationService
{
    public function __construct(private readonly PassengerCredentialDeliveryService $credentialDeliveryService) {}

    /**
     * Create or update a passenger in both mobile_users and users.
     */
    public function createOrUpdatePassenger(string $name, string $email, string $phone, string $deliveryChannel = 'email'): User
    {
        $name = trim($name);
        $email = trim($email);
        $phone = trim($phone);
        $firstName = (string) Str::of($name)->before(' ')->trim();
        $lastName = (string) Str::of($name)->after(' ')->trim();

        if ($firstName === '') {
            $firstName = $name !== '' ? $name : 'Passenger';
        }

        if ($lastName === '') {
            $lastName = 'Passenger';
        }

        $password = Str::random(12);

        $user = DB::transaction(function () use ($email, $phone, $firstName, $lastName, $password): User {
            $mobileUser = MobileUser::query()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $phone,
                    'password' => $password,
                    'role' => UserRole::PASSENGER,
                    'is_verified' => true,
                ]
            );

            return User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => trim($firstName.' '.$lastName),
                    'phone' => $phone,
                    'password' => $password,
                    'role' => UserRole::PASSENGER,
                    'mobile_user_id' => $mobileUser->id,
                    'is_verified' => true,
                    'is_approved' => true,
                    'approved_at' => now(),
                ]
            );
        });

        $this->credentialDeliveryService->send($user, $password, $deliveryChannel);

        return $user;
    }
}
