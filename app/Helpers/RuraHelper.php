<?php

namespace App\Helpers;

class RuraHelper
{
    // Normalization helper (like _norm in Python)
    public static function norm(string $value): string
    {
        $cleaned = preg_replace('/[^A-Z0-9 ]+/', ' ', strtoupper($value ?? ''));

        return trim(preg_replace('/\s+/', ' ', $cleaned));
    }
}
