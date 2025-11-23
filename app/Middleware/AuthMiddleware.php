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
        $user = Auth::requireAccess($token);
        $request->user = $user;
        
        return $next($request);
    }
}
?>
