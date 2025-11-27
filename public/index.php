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
$router->post('/api/posts/{id}/media', [PostController::class, 'updateWithMedia'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/posts/{id}/like', [PostController::class, 'like'], ['auth' => true]);
$router->post('/api/posts/{id}/comments', [PostController::class, 'createComment']); // Public access for guest comments
$router->get('/api/posts/{id}/comments', [PostController::class, 'getComments']);
$router->delete('/api/comments/{id}', [PostController::class, 'deleteComment'], ['auth' => true]);
$router->post('/api/posts/{id}/pin', [PostController::class, 'pin'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/posts/{id}/unpin', [PostController::class, 'unpin'], ['middleware' => [AdminMiddleware::class]]);

// Blog
use App\Controllers\BlogController;
use App\Controllers\BlogCategoryController;
use App\Controllers\BlogTagController;

$router->post('/api/blog/articles', [BlogController::class, 'create'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/blog/articles', [BlogController::class, 'list'], ['middleware' => [OptionalAuthMiddleware::class]]);
$router->get('/api/blog/articles/{slug}', [BlogController::class, 'get'], ['middleware' => [OptionalAuthMiddleware::class]]);
$router->put('/api/blog/articles/{id}', [BlogController::class, 'update'], ['middleware' => [AdminMiddleware::class]]);
$router->delete('/api/blog/articles/{id}', [BlogController::class, 'delete'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/blog/articles/{id}/publish', [BlogController::class, 'publish'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/blog/articles/{id}/auto-save', [BlogController::class, 'autoSave'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/blog/articles/{id}/revisions', [BlogController::class, 'getRevisions'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/blog/articles/{id}/restore/{revision}', [BlogController::class, 'restoreRevision'], ['middleware' => [AdminMiddleware::class]]);


$router->get('/api/blog/categories', [BlogCategoryController::class, 'list']);
$router->post('/api/blog/categories', [BlogCategoryController::class, 'create'], ['middleware' => [AdminMiddleware::class]]);

$router->get('/api/blog/tags', [BlogTagController::class, 'list']);
$router->post('/api/blog/tags', [BlogTagController::class, 'create'], ['middleware' => [AdminMiddleware::class]]);

// Blog Comments & Likes
use App\Controllers\BlogCommentController;

$router->get('/api/blog/articles/{id}/comments', [BlogCommentController::class, 'list']);
$router->post('/api/blog/articles/{id}/comments', [BlogCommentController::class, 'create']); // Guests allowed
$router->get('/api/blog/comments/pending', [BlogCommentController::class, 'getPending'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/blog/comments/{id}/approve', [BlogCommentController::class, 'approve'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/blog/comments/{id}/reject', [BlogCommentController::class, 'reject'], ['middleware' => [AdminMiddleware::class]]);
$router->delete('/api/blog/comments/{id}', [BlogCommentController::class, 'delete'], ['middleware' => [AdminMiddleware::class]]);

$router->post('/api/blog/articles/{id}/like', [BlogController::class, 'like']); // Guests allowed
$router->get('/api/blog/articles/{id}/like-status', [BlogController::class, 'getLikeStatus']);

// Blog SEO & Discovery
use App\Controllers\BlogFeedController;

$router->get('/api/blog/rss.xml', [BlogFeedController::class, 'rss']);
$router->get('/api/blog/sitemap.xml', [BlogFeedController::class, 'sitemap']);
$router->get('/api/blog/archives', [BlogFeedController::class, 'archives']);
$router->get('/api/blog/archives/{year}/{month}', [BlogFeedController::class, 'archiveArticles']);
$router->get('/api/blog/search', [BlogController::class, 'search']);


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