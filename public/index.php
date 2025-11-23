<?php
// 入口檔：初始化設定、autoload、router 註冊與執行

declare(strict_types=1);

// Enable error display for debugging (remove in production after setup)
ini_set('display_errors', 1);
error_reporting(E_ALL);

$root = dirname(__DIR__);

// Auto-redirect to installer if .env doesn't exist
if (!file_exists($root . '/.env')) {
    // Check if this is already the install page to avoid redirect loop
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, 'install.') === false && strpos($requestUri, 'check-env.') === false) {
        header('Location: /install.html');
        exit;
    }
}

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
use App\Core\ExceptionHandler;
use App\Middleware\CorsMiddleware;

// 初始化核心
Logger::init($config['log']['path']);
Database::init($config['db']);
Auth::init($config['jwt'], $config['app_url']);

$request = Request::fromGlobals();
$router = new Router($request);

// 添加全局中間件
$router->addGlobalMiddleware(new CorsMiddleware($config['frontend_origin']));

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

use App\Middleware\AdminMiddleware;
use App\Middleware\OptionalAuthMiddleware;

// Posts
$router->post('/api/posts', [PostController::class, 'create'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/posts', [PostController::class, 'list'], ['middleware' => [OptionalAuthMiddleware::class]]);
$router->get('/api/posts/{id}', [PostController::class, 'get'], ['middleware' => [OptionalAuthMiddleware::class]]);
$router->delete('/api/posts/{id}', [PostController::class, 'delete'], ['middleware' => [AdminMiddleware::class]]);
$router->patch('/api/posts/{id}', [PostController::class, 'update'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/posts/{id}/like', [PostController::class, 'like'], ['auth' => true]);
$router->post('/api/posts/{id}/comments', [PostController::class, 'createComment']); // Public access for guest comments
$router->get('/api/posts/{id}/comments', [PostController::class, 'getComments']);
$router->post('/api/posts/{id}/pin', [PostController::class, 'pin'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/posts/{id}/unpin', [PostController::class, 'unpin'], ['middleware' => [AdminMiddleware::class]]);

// Users
$router->get('/api/users/{email}', [UserController::class, 'show']); // User profile by email
$router->post('/api/users/me', [UserController::class, 'updateMe'], ['auth' => true]); // Update own profile (POST for file upload)

// 執行
try {
    $router->dispatch();
} catch (Throwable $e) {
    ExceptionHandler::handle($e);
}

?>