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
        // Add CORS headers for all error responses
        self::addCorsHeaders();
        
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

    /**
     * Add CORS headers to current response
     * This ensures error responses (404, 500, etc.) also have CORS headers
     */
    private static function addCorsHeaders(): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (!$origin) {
            return;
        }

        // Get allowed origins from config
        $config = config();
        $allowedOrigin = $config['frontend_origin'] ?? '*';
        $allowedOrigins = array_map('trim', explode(',', $allowedOrigin));
        
        // Check if origin is allowed
        $isAllowed = in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins);
        
        if ($isAllowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Vary: Origin');
        }
    }
}
?>
