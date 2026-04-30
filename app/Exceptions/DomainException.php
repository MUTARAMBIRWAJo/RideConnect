<?php

namespace App\Exceptions;

use Exception;

/**
 * DomainException represents a business logic violation.
 *
 * Used when domain rules (RidePolicy, DriverPolicy, etc.) are violated.
 * These are expected failures, not programming errors.
 */
class DomainException extends Exception
{
    /**
     * Error code for structured error responses.
     */
    protected string $errorCode;

    /**
     * Create a new DomainException instance.
     *
     * @param string $message
     * @param string $errorCode
     */
    public function __construct(string $message, string $errorCode = 'DOMAIN_ERROR')
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
    }

    /**
     * Static factory method for easy exception creation.
     *
     * @param string $message
     * @param string $errorCode
     * @return static
     */
    public static function make(string $message, string $errorCode = 'DOMAIN_ERROR'): static
    {
        return new static($message, $errorCode);
    }

    /**
     * Get the error code.
     *
     * @return string
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
