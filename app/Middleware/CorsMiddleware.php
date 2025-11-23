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
        
        if ($origin && (in_array($origin, $allowedOrigins) || in_array('*', $allowedOrigins))) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
            header('Vary: Origin');
        }

        // Handle preflight requests
        if ($request->method === 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
            http_response_code(204);
            exit;
        }

        return $next($request);
    }
}
?>
