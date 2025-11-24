<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use Closure;

/**
 * CORS middleware
 * Handles Cross-Origin Resource Sharing headers
 */
class CorsMiddleware implements Middleware {
    private string $allowedOrigin;

    public function __construct(string $allowedOrigin) {
        $this->allowedOrigin = $allowedOrigin;
    }

    public function handle(Request $request, Closure $next): mixed {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Support multiple allowed origins (comma separated)
        $allowedOrigins = array_map('trim', explode(',', $this->allowedOrigin));
        
        // Check if origin is allowed
        $isAllowed = $origin && (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins));
        
        if ($isAllowed) {
            // Set CORS headers for all requests (including preflight)
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 86400'); // Cache preflight for 24 hours
            header('Vary: Origin');
        }

        // Handle preflight OPTIONS requests
        if ($request->method === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        return $next($request);
    }
}
?>
