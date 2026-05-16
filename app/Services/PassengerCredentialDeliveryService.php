<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class PassengerCredentialDeliveryService
{
    public function send(User $user, string $plainPassword, string $channel): void
    {
        $normalizedChannel = strtolower(trim($channel));
        $attempts = match ($normalizedChannel) {
            'whatsapp' => ['whatsapp', 'sms', 'email'],
            'sms' => ['sms', 'email'],
            'email' => ['email'],
            default => ['email'],
        };

        $lastException = null;

        foreach ($attempts as $attemptChannel) {
            try {
                match ($attemptChannel) {
                    'email' => $this->sendEmail($user, $plainPassword),
                    'sms' => $this->sendSms($user, $plainPassword),
                    'whatsapp' => $this->sendWhatsApp($user, $plainPassword),
                    default => throw new RuntimeException('Unsupported passenger credential delivery channel.'),
                };

                if ($attemptChannel !== $normalizedChannel) {
                    Log::warning('Passenger credential delivery fell back to a different channel.', [
                        'requested_channel' => $normalizedChannel,
                        'used_channel' => $attemptChannel,
                        'user_id' => $user->id,
                    ]);
                }

                return;
            } catch (Throwable $exception) {
                $lastException = $exception;

                if (! $this->isConfigOrTransportException($exception)) {
                    throw $exception;
                }
            }
        }

        if ($lastException) {
            throw new RuntimeException($lastException->getMessage(), 0, $lastException);
        }
    }

    private function sendEmail(User $user, string $plainPassword): void
    {
        if (! $user->email) {
            throw new RuntimeException('Passenger email is missing for password delivery.');
        }

        try {
            Mail::raw($this->buildMessage($user, $plainPassword), function ($message) use ($user): void {
                $message
                    ->to($user->email)
                    ->subject('RideConnect Passenger Account Password');
            });

            return;
        } catch (Throwable $mailException) {
            Log::warning('Passenger credential email send failed, trying Supabase magic link fallback.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $mailException->getMessage(),
            ]);
        }

        $this->sendSupabaseMagicLink($user);
    }

    private function sendSms(User $user, string $plainPassword): void
    {
        $to = $this->normalizePhone($user->phone, false);
        $from = config('services.twilio.sms_from');

        if (! $to) {
            throw new RuntimeException('Passenger phone number is missing for SMS delivery.');
        }

        if (! $from) {
            throw new RuntimeException('TWILIO_SMS_FROM is not configured.');
        }

        $this->sendViaTwilio([
            'To' => $to,
            'From' => $from,
            'Body' => $this->buildMessage($user, $plainPassword),
        ]);
    }

    private function sendWhatsApp(User $user, string $plainPassword): void
    {
        $to = $this->normalizePhone($user->phone, true);
        $from = config('services.twilio.whatsapp_from');

        if (! $to) {
            throw new RuntimeException('Passenger phone number is missing for WhatsApp delivery.');
        }

        if (! $from) {
            throw new RuntimeException('TWILIO_WHATSAPP_FROM is not configured.');
        }

        $this->sendViaTwilio([
            'To' => 'whatsapp:'.$to,
            'From' => $from,
            'Body' => $this->buildMessage($user, $plainPassword),
        ]);
    }

    private function sendViaTwilio(array $payload): void
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.auth_token');

        if (! $sid || ! $token) {
            throw new RuntimeException('Twilio credentials are not configured.');
        }

        $response = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", $payload);

        if ($response->failed()) {
            $message = $response->json('message') ?: $response->body();

            throw new RuntimeException('Twilio send failed: '.$message);
        }
    }

    private function normalizePhone(?string $phone, bool $forWhatsApp): ?string
    {
        if (! $phone) {
            return null;
        }

        $clean = preg_replace('/\s+/', '', trim($phone));

        if (! $clean) {
            return null;
        }

        if (str_starts_with($clean, 'whatsapp:')) {
            $clean = str_replace('whatsapp:', '', $clean);
        }

        if (! str_starts_with($clean, '+')) {
            $defaultCountryCode = (string) config('services.twilio.default_country_code', '+250');
            $clean = $defaultCountryCode.ltrim($clean, '0');
        }

        return $forWhatsApp ? str_replace('whatsapp:', '', $clean) : $clean;
    }

    private function buildMessage(User $user, string $plainPassword): string
    {
        $name = trim((string) $user->name) ?: 'Passenger';

        return "Hello {$name}, your RideConnect passenger password is: {$plainPassword}. Please change it after first login.";
    }

    private function isConfigOrTransportException(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'not configured')
            || str_contains($message, 'missing for whatsapp delivery')
            || str_contains($message, 'missing for sms delivery')
            || str_contains($message, 'twilio send failed')
            || str_contains($message, 'connection could not be established')
            || str_contains($message, 'name does not resolve');
    }

    private function sendSupabaseMagicLink(User $user): void
    {
        /** @var SupabaseClient $supabase */
        $supabase = app(SupabaseClient::class);

        $response = $supabase->sendMagicLink((string) $user->email);

        if ($response->failed()) {
            $message = $response->json('msg')
                ?? $response->json('message')
                ?? $response->body();

            throw new RuntimeException('Supabase magic link fallback failed: '.$message);
        }

        Log::info('Passenger credential delivery fell back to Supabase magic link.', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }
}
