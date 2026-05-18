<?php

namespace App\Events\Domain;

class BusPositionUpdated
{
    public function __construct(public readonly int $updateId) {}
}