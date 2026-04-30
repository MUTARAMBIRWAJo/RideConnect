<?php

namespace App\Exceptions\Handlers;

use App\Exceptions\DomainException;
use Illuminate\Foundation\Configuration\Exceptions;

class DomainExceptionHandler
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_VIOLATION',
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        });
    }
}
