<?php

namespace App\Events\Domain;

class TicketIssued
{
    public function __construct(public readonly int $ticketId) {}
}
