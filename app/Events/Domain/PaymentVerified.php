<?php

namespace App\Events\Domain;

class PaymentVerified
{
    public function __construct(public readonly int $paymentId, public readonly ?int $tripId) {}
}
