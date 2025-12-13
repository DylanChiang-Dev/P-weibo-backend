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

    private function isOriginAllowed(string $origin, array $allowedOrigins): bool {
        if (!$origin) {
            return false;
        }

        if (in_array('*', $allowedOrigins, true)) {
            return true;
        }

        if (in_array($origin, $allowedOrigins, true)) {
            return true;
        }

        $originParts = parse_url($origin);
        if (!is_array($originParts) || !isset($originParts['scheme'], $originParts['host'])) {
            return false;
        }

        $originScheme = strtolower((string)$originParts['scheme']);
        $originHost = strtolower((string)$originParts['host']);
        $originPort = (int)($originParts['port'] ?? ($originScheme === 'https' ? 443 : 80));

        $loopbackHosts = ['localhost', '127.0.0.1', '::1'];
        if (!in_array($originHost, $loopbackHosts, true)) {
            return false;
        }

        // Developer convenience: treat localhost/127.0.0.1/::1 as equivalent for the same scheme+port.
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
                return true;
            }
        }

        return false;
    }

    public function handle(Request $request, Closure $next): mixed {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Support multiple allowed origins (comma separated)
        $allowedOrigins = array_map('trim', explode(',', $this->allowedOrigin));
        
        // Check if origin is allowed
        $isAllowed = $this->isOriginAllowed($origin, $allowedOrigins);
        
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
