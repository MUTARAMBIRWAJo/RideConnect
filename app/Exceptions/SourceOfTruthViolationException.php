<?php

namespace App\Exceptions;

use Exception;

class SourceOfTruthViolationException extends Exception
{
    public static function forField(string $domain, string $field): self
    {
        return new self("Violation: Attempted to write authoritative state for field '{$field}' in domain '{$domain}' to Firebase. Supabase PostgreSQL is the absolute source of truth.");
    }
}
