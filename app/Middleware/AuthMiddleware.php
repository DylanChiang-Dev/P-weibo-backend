<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Auth;
use Closure;

/**
 * Authentication middleware
 * Verifies the user's access token and attaches user info to request
 */
class AuthMiddleware implements Middleware {
    public function handle(Request $request, Closure $next): mixed {
        $token = $request->bearerToken();
        
        // Debug logging
        \App\Core\Logger::info('auth_middleware_check', [
            'path' => $request->path,
            'method' => $request->method,
            'has_token' => $token !== null,
            'token_preview' => $token ? substr($token, 0, 20) . '...' : null,
            'headers' => array_keys($request->headers)
        ]);
        
        $user = Auth::requireAccess($token);
        $request->user = $user;
        
        return $next($request);
    }
}
?>
