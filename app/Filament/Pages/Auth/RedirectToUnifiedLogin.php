<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login;

class RedirectToUnifiedLogin extends Login
{
    public function mount(): void
    {
        $this->redirect(route('auth.login'), navigate: true);
    }
}
