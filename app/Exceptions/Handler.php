<?php

namespace App\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PDOException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // Handle database exceptions for API requests
        if ($request->wantsJson()) {
            if ($exception instanceof QueryException || $exception instanceof PDOException) {
                // Log the actual error
                \Log::error('Database error:', [
                    'message' => $exception->getMessage(),
                    'code' => $exception->getCode(),
                ]);

                // Return user-friendly response
                return response()->json([
                    'success' => false,
                    'message' => 'A service error occurred. Please try again later.',
                ], 500);
            }
        }

        return parent::render($request, $exception);
    }
}
