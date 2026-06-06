<?php

namespace App\Exceptions;

use Exception;

class GeocodingException extends Exception
{
    public function __construct(string $message = 'Geocoding failed', int $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'message' => $this->message,
            'error_code' => 'GEOCODING_FAILED',
        ], 422);
    }
}
