<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Auth;
use App\Core\Response;
use App\Exceptions\UnauthorizedException;
use App\Services\TokenService;
use Closure;

/**
 * Authentication middleware
 * Verifies the user's access token and attaches user info to request
 */
class AuthMiddleware implements Middleware {
    public function handle(Request $request, Closure $next): mixed {
        $token = $request->bearerToken() ?? ($request->cookies['access_token'] ?? null);

        try {
            $user = Auth::requireAccess($token);
            $request->user = $user;
            return $next($request);
        } catch (\Throwable $e) {
            // Fallback: cookie-based refresh flow (no Authorization header required)
            $refresh = $request->cookies['refresh_token'] ?? null;
            if (!$refresh) {
                throw new UnauthorizedException('Invalid or expired token');
            }

            $config = \config();
            $cookieBase = [
                'path' => '/',
                'domain' => $config['cookie']['domain'] ?? '',
                'secure' => (bool)($config['cookie']['secure'] ?? ($config['app_env'] !== 'development')),
                'httponly' => true,
                'samesite' => $config['cookie']['samesite'] ?? 'None',
            ];

            try {
                $tokens = TokenService::refresh($refresh);

                Response::setCookie('refresh_token', $tokens['refresh_token'], $cookieBase + [
                    'expires' => time() + (int)$config['jwt']['refresh_ttl'],
                ]);
                Response::setCookie('access_token', $tokens['access_token'], $cookieBase + [
                    'expires' => time() + (int)$config['jwt']['access_ttl'],
                ]);

                $request->user = Auth::requireAccess($tokens['access_token']);
                return $next($request);
            } catch (\Throwable $refreshError) {
                Response::clearCookie('refresh_token', $cookieBase);
                Response::clearCookie('access_token', $cookieBase);
                throw new UnauthorizedException('Invalid or expired token');
            }
        }
        
    }
}
?>
