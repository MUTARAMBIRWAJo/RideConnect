<?php

namespace App\Services;

use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BookingReceiptDeliveryService
{
    public function generatePdfBinary(Booking $booking): string
    {
        $booking->loadMissing(['user', 'ride.driver']);

        return Pdf::loadView('pdf.booking-receipt', [
            'booking' => $booking,
            'user' => $booking->user,
            'ride' => $booking->ride,
        ])->setPaper('a4')->output();
    }

    public function storeReceiptPdf(Booking $booking): array
    {
        $binary = $this->generatePdfBinary($booking);
        $fileName = 'booking-receipt-' . $booking->id . '.pdf';
        $path = 'receipts/bookings/' . $fileName;

        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'binary' => $binary,
            'file_name' => $fileName,
        ];
    }

    public function sendToEmail(Booking $booking): void
    {
        $booking->loadMissing(['user']);
        $recipient = $booking->user?->email;

        if (! $recipient) {
            throw new RuntimeException('User email is missing for this booking.');
        }

        $receipt = $this->storeReceiptPdf($booking);

        Mail::send([], [], function ($message) use ($recipient, $booking, $receipt) {
            $message
                ->to($recipient)
                ->subject('RideConnect Booking Receipt #' . $booking->id)
                ->html($this->buildEmailHtml($booking, $receipt['url']))
                ->attachData($receipt['binary'], $receipt['file_name'], ['mime' => 'application/pdf']);
        });
    }

    public function sendToWhatsApp(Booking $booking): void
    {
        $booking->loadMissing(['user']);

        $to = $this->normalizePhone($booking->user?->phone, true);
        $from = config('services.twilio.whatsapp_from');

        if (! $to) {
            throw new RuntimeException('User phone number is missing for WhatsApp delivery.');
        }

        if (! $from) {
            throw new RuntimeException('TWILIO_WHATSAPP_FROM is not configured.');
        }

        $receipt = $this->storeReceiptPdf($booking);

        $this->sendViaTwilio([
            'To' => 'whatsapp:' . $to,
            'From' => $from,
            'Body' => 'RideConnect Booking Receipt #' . $booking->id . ' attached. Download link: ' . $receipt['url'],
            'MediaUrl' => $receipt['url'],
        ]);
    }

    public function sendToSms(Booking $booking): void
    {
        $booking->loadMissing(['user']);

        $to = $this->normalizePhone($booking->user?->phone, false);
        $from = config('services.twilio.sms_from');

        if (! $to) {
            throw new RuntimeException('User phone number is missing for SMS delivery.');
        }

        if (! $from) {
            throw new RuntimeException('TWILIO_SMS_FROM is not configured.');
        }

        $receipt = $this->storeReceiptPdf($booking);

        $this->sendViaTwilio([
            'To' => $to,
            'From' => $from,
            'Body' => 'RideConnect Booking Receipt #' . $booking->id . ': ' . $receipt['url'],
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
            throw new RuntimeException('Twilio send failed: ' . $message);
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
            $clean = $defaultCountryCode . ltrim($clean, '0');
        }

        return $forWhatsApp ? str_replace('whatsapp:', '', $clean) : $clean;
    }

    private function buildEmailHtml(Booking $booking, string $receiptUrl): string
    {
        return '<div style="font-family:Arial,sans-serif;color:#0f172a;line-height:1.5">'
            . '<h2 style="margin:0 0 12px">RideConnect Booking Receipt</h2>'
            . '<p style="margin:0 0 8px">Your receipt for booking <strong>#' . $booking->id . '</strong> is attached as PDF.</p>'
            . '<p style="margin:0 0 8px">You can also download it here: <a href="' . e($receiptUrl) . '">' . e($receiptUrl) . '</a></p>'
            . '<p style="margin:12px 0 0">Thank you,<br>RideConnect Team</p>'
            . '</div>';
    }
}
