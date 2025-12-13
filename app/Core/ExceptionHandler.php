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
        
        // Check if origin is allowed (including localhost/127.0.0.1 developer convenience)
        $isAllowed = in_array('*', $allowedOrigins, true) || in_array($origin, $allowedOrigins, true);

        if (!$isAllowed) {
            $originParts = parse_url($origin);
            if (is_array($originParts) && isset($originParts['scheme'], $originParts['host'])) {
                $originScheme = strtolower((string)$originParts['scheme']);
                $originHost = strtolower((string)$originParts['host']);
                $originPort = (int)($originParts['port'] ?? ($originScheme === 'https' ? 443 : 80));

                $loopbackHosts = ['localhost', '127.0.0.1', '::1'];
                if (in_array($originHost, $loopbackHosts, true)) {
                    foreach ($allowedOrigins as $allowed) {
                        if ($allowed === '' || $allowed === '*') {
                            continue;
                        }

                        $allowedParts = parse_url($allowed);
                        if (!is_array($allowedParts) || !isset($allowedParts['scheme'], $allowedParts['host'])) {
                            continue;
                        }

                        $allowedScheme = strtolower((string)$allowedParts['scheme']);
                        $allowedHost = strtolower((string)$allowedParts['host']);
                        $allowedPort = (int)($allowedParts['port'] ?? ($allowedScheme === 'https' ? 443 : 80));

                        if (
                            $allowedScheme === $originScheme &&
                            $allowedPort === $originPort &&
                            in_array($allowedHost, $loopbackHosts, true)
                        ) {
                            $isAllowed = true;
                            break;
                        }
                    }
                }
            }
        }
        
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
