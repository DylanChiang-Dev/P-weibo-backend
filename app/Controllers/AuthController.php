<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\ApiResponse;
use App\Core\Validator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Services\AuthService;
use App\Services\TokenService;
use App\Services\RateLimitService;
use App\Models\User;

class AuthController {
    private AuthService $authService;

    public function __construct() {
        $config = \config();
        TokenService::init($config['jwt']);
        $this->authService = new AuthService();
    }

    private function cookieBaseOptions(array $config): array {
        return [
            'path' => '/',
            'domain' => $config['cookie']['domain'] ?? '',
            'secure' => (bool)($config['cookie']['secure'] ?? ($config['app_env'] !== 'development')),
            'httponly' => true,
            'samesite' => $config['cookie']['samesite'] ?? 'None',
        ];
    }

    public function register(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        $errs = Validator::required($data, ['email', 'password']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);
        if (!Validator::email($data['email'])) throw new ValidationException('Invalid email format');
        if (!Validator::length($data['password'], 8, 255)) throw new ValidationException('Password must be at least 8 characters');

        $config = \config();
        $limiter = new RateLimitService(
            $config['log']['path'] . '/ratelimit',
            $config['app_env'] === 'production' ? 5 : 1000,
            $config['app_env'] === 'production' ? 600 : 60
        );

        if (!$limiter->check($data['email'])) {
            ApiResponse::error('Too many registration attempts. Please try again later.', 429);
        }

        $result = $this->authService->register($data['email'], $data['password']);
        ApiResponse::success($result, 201);
    }

    public function login(Request $req): void {
        $config = \config();
        // TEMPORARILY DISABLED: Rate Limit for login (for development/migration scripts)
        // Uncomment when done with migration
        // $limiter = new RateLimitService(
        //     $config['log']['path'] . '/ratelimit',
        //     $config['app_env'] === 'production' ? 5 : 1000,
        //     $config['app_env'] === 'production' ? 600 : 60
        // );
        // if (!$limiter->check('login:' . $req->ip())) {
        //     ApiResponse::error('Too many login attempts. Please try again later.', 429);
        // }
        $data = is_array($req->body) ? $req->body : [];
        $errs = Validator::required($data, ['email', 'password']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);

        $tokens = $this->authService->login($data['email'], $data['password'], $req->userAgent(), $req->ip());

        $cookieBase = $this->cookieBaseOptions($config);
        Response::setCookie('refresh_token', $tokens['refresh_token'], $cookieBase + [
            'expires' => time() + (int)$config['jwt']['refresh_ttl'],
        ]);
        Response::setCookie('access_token', $tokens['access_token'], $cookieBase + [
            'expires' => time() + (int)$config['jwt']['access_ttl'],
        ]);

        unset($tokens['refresh_token']);
        ApiResponse::success($tokens);
    }

    public function refresh(Request $req): void {
        $config = \config();
        $refresh = $req->cookies['refresh_token'] ?? (is_array($req->body) ? ($req->body['refresh_token'] ?? null) : null);
        
        if (!$refresh) {
            $cookieBase = $this->cookieBaseOptions($config);
            Response::clearCookie('refresh_token', $cookieBase);
            Response::clearCookie('access_token', $cookieBase);
            ApiResponse::error('Missing refresh token', 401);
        }

        try {
            $tokens = $this->authService->refresh($refresh);
            $cookieBase = $this->cookieBaseOptions($config);
            Response::setCookie('refresh_token', $tokens['refresh_token'], $cookieBase + [
                'expires' => time() + (int)$config['jwt']['refresh_ttl'],
            ]);
            Response::setCookie('access_token', $tokens['access_token'], $cookieBase + [
                'expires' => time() + (int)$config['jwt']['access_ttl'],
            ]);
            unset($tokens['refresh_token']);
            ApiResponse::success($tokens);
        } catch (\Throwable $e) {
             $cookieBase = $this->cookieBaseOptions($config);
             Response::clearCookie('refresh_token', $cookieBase);
             Response::clearCookie('access_token', $cookieBase);
             ApiResponse::error('Refresh token invalid or expired', 401);
        }
    }

    public function logout(Request $req): void {
        $config = \config();
        $refresh = $req->cookies['refresh_token'] ?? (is_array($req->body) ? ($req->body['refresh_token'] ?? null) : null);
        if ($refresh) {
            $this->authService->logout($refresh);
        }
        $cookieBase = $this->cookieBaseOptions($config);
        Response::clearCookie('refresh_token', $cookieBase);
        Response::clearCookie('access_token', $cookieBase);
        ApiResponse::success();
    }

    public function me(Request $req): void {
        $user = User::findById((int)$req->user['id']);
        if (!$user) throw new NotFoundException('User not found');
        ApiResponse::success($user);
    }
}
?>
