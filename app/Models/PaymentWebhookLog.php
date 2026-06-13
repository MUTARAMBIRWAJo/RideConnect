<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentWebhookLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'log_id',
        'payment_provider',
        'webhook_id',
        'event_type',
        'source_ip',
        'signature',
        'signature_valid',
        'headers',
        'payload',
        'http_status_code',
        'response_body',
        'error_message',
        'processing_status',
        'processed_at',
        'received_at',
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'processed_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    public $timestamps = true;
}
