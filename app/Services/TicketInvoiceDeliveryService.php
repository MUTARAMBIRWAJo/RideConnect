<?php

namespace App\Services;

use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TicketInvoiceDeliveryService
{
    public function generatePdfBinary(Ticket $ticket): string
    {
        $ticket->loadMissing(['trip.passenger', 'issuer']);

        return Pdf::loadView('pdf.ticket-invoice', [
            'ticket' => $ticket,
            'trip' => $ticket->trip,
            'passenger' => $ticket->trip?->passenger,
            'issuer' => $ticket->issuer,
        ])->setPaper('a4')->output();
    }

    public function storeInvoicePdf(Ticket $ticket): array
    {
        $binary = $this->generatePdfBinary($ticket);
        $fileName = 'ticket-invoice-' . $ticket->id . '.pdf';
        $path = 'invoices/tickets/' . $fileName;

        Storage::disk('public')->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'binary' => $binary,
            'file_name' => $fileName,
        ];
    }

    public function sendToEmail(Ticket $ticket): void
    {
        $ticket->loadMissing(['trip.passenger']);
        $recipient = $ticket->trip?->passenger?->email;

        if (! $recipient) {
            throw new RuntimeException('Passenger email is missing for this ticket.');
        }

        $invoice = $this->storeInvoicePdf($ticket);

        Mail::send([], [], function ($message) use ($recipient, $ticket, $invoice) {
            $message
                ->to($recipient)
                ->subject('RideConnect Ticket Invoice #' . $ticket->id)
                ->html($this->buildEmailHtml($ticket, $invoice['url']))
                ->attachData($invoice['binary'], $invoice['file_name'], ['mime' => 'application/pdf']);
        });
    }

    public function sendToWhatsApp(Ticket $ticket): void
    {
        $ticket->loadMissing(['trip.passenger']);

        $to = $this->normalizePhone($ticket->trip?->passenger?->phone, true);
        $from = config('services.twilio.whatsapp_from');

        if (! $to) {
            throw new RuntimeException('Passenger phone number is missing for WhatsApp delivery.');
        }

        if (! $from) {
            throw new RuntimeException('TWILIO_WHATSAPP_FROM is not configured.');
        }

        $invoice = $this->storeInvoicePdf($ticket);

        $this->sendViaTwilio([
            'To' => 'whatsapp:' . $to,
            'From' => $from,
            'Body' => 'RideConnect Ticket Invoice #' . $ticket->id . ' attached. Download link: ' . $invoice['url'],
            'MediaUrl' => $invoice['url'],
        ]);
    }

    public function sendToSms(Ticket $ticket): void
    {
        $ticket->loadMissing(['trip.passenger']);

        $to = $this->normalizePhone($ticket->trip?->passenger?->phone, false);
        $from = config('services.twilio.sms_from');

        if (! $to) {
            throw new RuntimeException('Passenger phone number is missing for SMS delivery.');
        }

        if (! $from) {
            throw new RuntimeException('TWILIO_SMS_FROM is not configured.');
        }

        $invoice = $this->storeInvoicePdf($ticket);

        $this->sendViaTwilio([
            'To' => $to,
            'From' => $from,
            'Body' => 'RideConnect Ticket Invoice #' . $ticket->id . ': ' . $invoice['url'],
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

    private function buildEmailHtml(Ticket $ticket, string $invoiceUrl): string
    {
        return '<div style="font-family:Arial,sans-serif;color:#0f172a;line-height:1.5">'
            . '<h2 style="margin:0 0 12px">RideConnect Ticket Invoice</h2>'
            . '<p style="margin:0 0 8px">Your invoice for ticket <strong>#' . $ticket->id . '</strong> is attached as PDF.</p>'
            . '<p style="margin:0 0 8px">You can also download it here: <a href="' . e($invoiceUrl) . '">' . e($invoiceUrl) . '</a></p>'
            . '<p style="margin:12px 0 0">Thank you,<br>RideConnect Team</p>'
            . '</div>';
    }
}
