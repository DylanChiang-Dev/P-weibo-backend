<?php
namespace App\Core;

use App\Exceptions\AppException;
use Throwable;

/**
 * Global exception handler
 * Handles all exceptions and returns standardized JSON responses
 */
class ExceptionHandler {
    /**
     * Handle an exception and send appropriate response
     */
    public static function handle(Throwable $e): void {
        // Log the exception
        Logger::error('exception', [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        // Determine status code and error details
        if ($e instanceof AppException) {
            $statusCode = $e->getStatusCode();
            $message = $e->getMessage();
            $details = $e->getErrorDetails();
        } else {
            // Unknown exception - don't leak internal details
            $statusCode = 500;
            $message = 'Internal Server Error';
            $details = null;
        }

        // Send error response
        ApiResponse::error($message, $statusCode, $details);
    }
}
?>
