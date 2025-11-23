<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Auth;
use Closure;

/**
 * Optional Authentication Middleware
 * Attempts to authenticate user if token is present, but doesn't throw error if missing
 * This allows endpoints to be accessible to both authenticated and anonymous users
 */
class OptionalAuthMiddleware implements Middleware {
    public function handle(Request $request, Closure $next): mixed {
        $token = $request->bearerToken();
        
        if ($token) {
            try {
                $user = Auth::requireAccess($token);
                $request->user = $user;
            } catch (\Throwable $e) {
                // Token is invalid, but we don't fail the request
                // Just continue without user context
                $request->user = null;
            }
        } else {
            // No token provided, continue as anonymous
            $request->user = null;
        }
        
        return $next($request);
    }
}
?>
