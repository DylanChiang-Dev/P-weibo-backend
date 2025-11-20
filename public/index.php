<?php
// 入口檔：初始化設定、autoload、router 註冊與執行

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
$config = config();

// Autoload：將 App\Name\Class 對應到 app/Name/Class.php
spl_autoload_register(function (string $class) use ($root) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $path = $root . '/app/' . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($path)) require_once $path;
});

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Logger;
use App\Core\Database;
use App\Core\Auth;

// 初始化核心
Logger::init($config['log']['path']);
Database::init($config['db']);
Auth::init($config['jwt'], $config['app_url']);

$request = Request::fromGlobals();
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && $origin === $config['frontend_origin']) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}
if ($request->method === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    http_response_code(204);
    exit;
}
$router = new Router($request);

// 共用 middleware：簡易 rate limit（登入等）可在控制器呼叫，auth 由 Router 支援

// 路由註冊
use App\Controllers\AuthController;
use App\Controllers\PostController;
use App\Controllers\UserController;

// Auth
// Registration disabled - this is a single-user personal blog
// $router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->post('/api/refresh', [AuthController::class, 'refresh']);
$router->post('/api/token/refresh', [AuthController::class, 'refresh']); // New endpoint
$router->post('/api/logout', [AuthController::class, 'logout'], ['auth' => true]);
$router->get('/api/me', [AuthController::class, 'me'], ['auth' => true]);

// Posts
$router->post('/api/posts', [PostController::class, 'create'], ['auth' => true]);
$router->get('/api/posts', [PostController::class, 'list']);
$router->get('/api/posts/{id}', [PostController::class, 'get']);
$router->delete('/api/posts/{id}', [PostController::class, 'delete'], ['auth' => true]); // New route for deleting a post
$router->post('/api/posts/{id}/like', [PostController::class, 'like'], ['auth' => true]);
$router->post('/api/posts/{id}/comments', [PostController::class, 'createComment']); // Public access for guest comments
$router->get('/api/posts/{id}/comments', [PostController::class, 'getComments']);
$router->post('/api/posts/{id}/pin', [PostController::class, 'pin'], ['auth' => true]); // Pin post
$router->post('/api/posts/{id}/unpin', [PostController::class, 'unpin'], ['auth' => true]); // Unpin post

// Users
$router->get('/api/users/{email}', [UserController::class, 'show']); // User profile by email
$router->post('/api/users/me', [UserController::class, 'updateMe'], ['auth' => true]); // Update own profile (POST for file upload)

// 執行
try {
    $router->dispatch();
} catch (Throwable $e) {
    Logger::error('unhandled_exception', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    Response::json(['success' => false, 'error' => 'Internal Server Error'], 500);
}

?>