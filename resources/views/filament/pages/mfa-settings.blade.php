<x-filament-panels::page>
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Two-Factor Authentication</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    @if ($hasMfaEnabled)
                        Your account is protected with two-factor authentication.
                    @else
                        Add an extra layer of security to your account.
                    @endif
                </p>
            </div>

            @if ($hasMfaEnabled)
                <x-filament::button
                    color="danger"
                    size="sm"
                    wire:click="disableMfa"
                    wire:confirm="Are you sure you want to disable two-factor authentication?"
                >
                    Disable MFA
                </x-filament::button>
            @endif
        </div>

        @if ($showBackupCodes && count($backupCodes) > 0)
            <!-- Backup Codes -->
            <x-filament::card>
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Your Backup Codes
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Save these backup codes in a safe place. Each code can be used once to access your account if you lose access to your authenticator app.
                    </p>

                    <div class="mt-4 grid grid-cols-2 gap-3">
                        @foreach ($backupCodes as $code)
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-3 text-center font-mono text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-filament::button
                            color="primary"
                            wire:click="refreshSetup"
                            wire:confirm="Generating new backup codes will invalidate the current ones. Continue?"
                        >
                            Generate New Codes
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::card>
        @endif

        @if (! $hasMfaEnabled)
            <!-- Setup Instructions -->
            <x-filament::card>
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Step 1: Install an Authenticator App
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Install an authenticator app on your phone (Google Authenticator, Authy, or Microsoft Authenticator).
                    </p>
                </div>
            </x-filament::card>

            <!-- QR Code Card -->
            <x-filament::card>
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Step 2: Scan QR Code
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Scan this QR code with your authenticator app:
                    </p>

                    @if (isset($qrCode))
                        <div class="mt-4 flex justify-center">
                            <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                                {!! $qrCode !!}
                            </div>
                        </div>
                    @endif

                    @if (isset($secret))
                        <div class="mt-4">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                Or enter this secret manually:
                            </p>
                            <div class="mt-2 rounded-lg border border-gray-300 bg-gray-50 p-3 text-center font-mono text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                {{ $secret }}
                            </div>
                        </div>
                    @endif

                    <div class="mt-4">
                        <x-filament::button
                            color="secondary"
                            wire:click="refreshSetup"
                        >
                            Generate New QR Code
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::card>

            <!-- Verification -->
            <x-filament::card>
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Step 3: Verify Setup
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                        Enter the 6-digit code from your authenticator app to confirm the setup:
                    </p>

                    <div class="mt-4">
                        {{ $this->form }}
                        
                        <div class="mt-4">
                            <x-filament::button
                                color="success"
                                wire:click="setupMfa"
                            >
                                Enable Two-Factor Authentication
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </x-filament::card>
        @endif
    </div>
</x-filament-panels::page>
