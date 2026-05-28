<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OfficerCreateBookingTrip extends Page
{
    protected static ?string $navigationLabel = 'Create Booking/Trip';

    protected static string $view = 'filament.pages.officer-create-booking-trip';

    protected static ?string $title = 'Create Booking or Trip for Passenger';

    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationGroup = 'Live Operations';

    public static function canAccess(): bool
    {
        return auth()->user()?->role?->value === 'OFFICER' ||
               auth()->user()?->role?->value === 'ADMIN' ||
               auth()->user()?->role?->value === 'SUPER_ADMIN';
    }
}
