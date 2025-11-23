<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Auth;
use App\Exceptions\ForbiddenException;
use App\Exceptions\UnauthorizedException;
use App\Models\User;

class AdminMiddleware implements Middleware {
    public function handle(Request $request, \Closure $next): mixed {
        // First, authenticate the user
        $token = $request->bearerToken();
        $user = Auth::requireAccess($token);
        
        // Fetch full user details including role
        $fullUser = User::findById((int)$user['id']);
        if (!$fullUser) {
            throw new UnauthorizedException();
        }
        
        // Check for admin role
        if (!User::isAdmin($fullUser)) {
            throw new ForbiddenException('Admin access required');
        }
        
        // Attach full user info to request
        $request->user = $fullUser;

        return $next($request);
    }
}

