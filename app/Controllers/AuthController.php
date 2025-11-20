<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
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

    public function register(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        $errs = Validator::required($data, ['email', 'password']);
        if (!empty($errs)) Response::json(['success' => false, 'error' => 'Bad Request', 'details' => $errs], 400);
        if (!Validator::email($data['email'])) Response::json(['success' => false, 'error' => 'Bad email'], 400);
        if (!Validator::length($data['password'], 8, 255)) Response::json(['success' => false, 'error' => 'Weak password'], 400);

        $config = \config();
        $limiter = new RateLimitService(
            $config['log']['path'] . '/ratelimit',
            $config['app_env'] === 'production' ? 5 : 1000,
            $config['app_env'] === 'production' ? 600 : 60
        );

        if (!$limiter->check($data['email'])) {
            Response::json(['success' => false, 'error' => 'Too many attempts'], 429);
        }

        try {
            $result = $this->authService->register($data['email'], $data['password']);
            Response::json(['success' => true, 'data' => $result], 201);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], $e->getCode() ?: 400);
        }
    }

    public function login(Request $req): void {
        $config = \config();
        $limiter = new RateLimitService(
            $config['log']['path'] . '/ratelimit',
            $config['app_env'] === 'production' ? 5 : 1000,
            $config['app_env'] === 'production' ? 600 : 60
        );
        if (!$limiter->check('login:' . $req->ip())) {
            Response::json(['success' => false, 'error' => 'Too Many Requests'], 429);
        }
        $data = is_array($req->body) ? $req->body : [];
        $errs = Validator::required($data, ['email', 'password']);
        if (!empty($errs)) Response::json(['success' => false, 'error' => 'Bad Request'], 400);

        try {
            $tokens = $this->authService->login($data['email'], $data['password'], $req->userAgent(), $req->ip());
            
            Response::setCookie('refresh_token', $tokens['refresh_token'], [
                'expires' => time() + $config['jwt']['refresh_ttl'],
                'path' => '/', 'secure' => $config['app_env'] !== 'development', 'httponly' => true, 'samesite' => 'None'
            ]);
            unset($tokens['refresh_token']);
            Response::json(['success' => true, 'data' => $tokens]);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], $e->getCode() ?: 401);
        }
    }

    public function refresh(Request $req): void {
        $config = \config();
        $refresh = $req->cookies['refresh_token'] ?? (is_array($req->body) ? ($req->body['refresh_token'] ?? null) : null);
        
        if (!$refresh) {
            Response::clearCookie('refresh_token');
            Response::json(['success' => false, 'error' => 'Missing refresh token'], 401);
        }

        try {
            $tokens = $this->authService->refresh($refresh);
            Response::setCookie('refresh_token', $tokens['refresh_token'], [
                'expires' => time() + $config['jwt']['refresh_ttl'],
                'path' => '/', 'secure' => $config['app_env'] !== 'development', 'httponly' => true, 'samesite' => 'None'
            ]);
            unset($tokens['refresh_token']);
            Response::json(['success' => true, 'data' => $tokens]);
        } catch (\Exception $e) {
             Response::clearCookie('refresh_token');
             Response::json(['success' => false, 'error' => $e->getMessage()], 401);
        }
    }

    public function logout(Request $req): void {
        $refresh = $req->cookies['refresh_token'] ?? (is_array($req->body) ? ($req->body['refresh_token'] ?? null) : null);
        if ($refresh) {
            $this->authService->logout($refresh);
        }
        Response::clearCookie('refresh_token');
        Response::json(['success' => true]);
    }

    public function me(Request $req): void {
        $user = User::findById((int)$req->user['id']);
        if (!$user) Response::json(['success' => false, 'error' => 'Not Found'], 404);
        Response::json(['success' => true, 'data' => $user]);
    }
}
?>