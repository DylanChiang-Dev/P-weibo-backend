<?php
// 入口檔：初始化設定、autoload、router 註冊與執行

declare(strict_types=1);

// Error reporting (avoid leaking details in production)
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

// Enable/disable error display based on environment
ini_set('display_errors', ($config['app_env'] ?? 'development') === 'production' ? '0' : '1');

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
use App\Services\TokenService;
use App\Controllers\AuthController;
use App\Controllers\PostController;
use App\Controllers\UserController;
use App\Controllers\BlogController;
use App\Controllers\BlogCategoryController;
use App\Controllers\BlogTagController;
use App\Controllers\BlogCommentController;
use App\Controllers\BlogFeedController;
use App\Controllers\MediaController;
use App\Controllers\ActivityController;
use App\Controllers\MediaLibraryController;
use App\Controllers\UserSettingsController;
use App\Middleware\AdminMiddleware;
use App\Middleware\OptionalAuthMiddleware;

// Create request and apply CORS headers as early as possible.
// This ensures preflight and error responses still include CORS even if DB init fails.
$request = Request::fromGlobals();
$cors = new CorsMiddleware($config['frontend_origin']);
$cors->handle($request, fn ($req) => null);

// 初始化核心 + 路由註冊與執行
try {
    Logger::init($config['log']['path']);
    Database::init($config['db']);
    Auth::init($config['jwt'], $config['app_url']);
    TokenService::init($config['jwt']);

    $router = new Router($request);

    // 添加全局中間件（保留：讓路由層也能覆蓋 CORS/OPTIONS 行為）
    $router->addGlobalMiddleware(new CorsMiddleware($config['frontend_origin']));

// 路由註冊
// Auth
// Registration disabled - this is a single-user personal blog
// $router->post('/api/register', [AuthController::class, 'register']);
$router->post('/api/login', [AuthController::class, 'login']);
$router->post('/api/refresh', [AuthController::class, 'refresh']);
$router->post('/api/token/refresh', [AuthController::class, 'refresh']); // New endpoint
$router->post('/api/logout', [AuthController::class, 'logout'], ['auth' => true]);
$router->get('/api/me', [AuthController::class, 'me'], ['auth' => true]);

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
$router->post('/api/blog/articles', [BlogController::class, 'create'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/blog/articles', [BlogController::class, 'list'], ['middleware' => [OptionalAuthMiddleware::class]]);
$router->get('/api/blog/articles/{slug}', [BlogController::class, 'get'], ['middleware' => [OptionalAuthMiddleware::class]]);
$router->post('/api/blog/articles/{id}/view', [BlogController::class, 'incrementView'], ['middleware' => [OptionalAuthMiddleware::class]]);
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
$router->get('/api/blog/articles/{id}/comments', [BlogCommentController::class, 'list']);
$router->post('/api/blog/articles/{id}/comments', [BlogCommentController::class, 'create']); // Guests allowed
$router->get('/api/blog/comments/pending', [BlogCommentController::class, 'getPending'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/blog/comments/{id}/approve', [BlogCommentController::class, 'approve'], ['middleware' => [AdminMiddleware::class]]);
$router->post('/api/blog/comments/{id}/reject', [BlogCommentController::class, 'reject'], ['middleware' => [AdminMiddleware::class]]);
$router->delete('/api/blog/comments/{id}', [BlogCommentController::class, 'delete'], ['middleware' => [AdminMiddleware::class]]);

$router->post('/api/blog/articles/{id}/like', [BlogController::class, 'like']); // Guests allowed
$router->get('/api/blog/articles/{id}/like-status', [BlogController::class, 'getLikeStatus']);

// Blog SEO & Discovery
$router->get('/api/blog/rss.xml', [BlogFeedController::class, 'rss']);
$router->get('/api/blog/sitemap.xml', [BlogFeedController::class, 'sitemap']);
$router->get('/api/blog/archives', [BlogFeedController::class, 'archives']);
$router->get('/api/blog/archives/{year}/{month}', [BlogFeedController::class, 'archiveArticles']);
$router->get('/api/blog/search', [BlogController::class, 'search']);


// Media
$router->get('/api/media', [MediaController::class, 'list'], ['auth' => true]);
$router->post('/api/media', [MediaController::class, 'upload'], ['auth' => true]);
$router->delete('/api/media/{id}', [MediaController::class, 'delete'], ['auth' => true]);

// Activities
$router->post('/api/activities/checkin', [ActivityController::class, 'checkin'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/activities/heatmap', [ActivityController::class, 'heatmap']); // Public
$router->get('/api/activities/stats', [ActivityController::class, 'stats']); // Public
$router->get('/api/activities/daily', [ActivityController::class, 'daily']); // Public

// Media Library
// Movies
$router->get('/api/library/movies', [MediaLibraryController::class, 'listMovies']); // Public
$router->post('/api/library/movies', [MediaLibraryController::class, 'addMovie'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/library/movies/{id}', [MediaLibraryController::class, 'getMovie']); // Public
$router->put('/api/library/movies/{id}', [MediaLibraryController::class, 'updateMovie'], ['middleware' => [AdminMiddleware::class]]);
$router->delete('/api/library/movies/{id}', [MediaLibraryController::class, 'deleteMovie'], ['middleware' => [AdminMiddleware::class]]);

// TV Shows
$router->get('/api/library/tv-shows', [MediaLibraryController::class, 'listTvShows']); // Public
$router->post('/api/library/tv-shows', [MediaLibraryController::class, 'addTvShow'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/library/tv-shows/{id}', [MediaLibraryController::class, 'getTvShow']); // Public
$router->put('/api/library/tv-shows/{id}', [MediaLibraryController::class, 'updateTvShow'], ['middleware' => [AdminMiddleware::class]]);
$router->patch('/api/library/tv-shows/{id}/progress', [MediaLibraryController::class, 'updateTvShowProgress'], ['middleware' => [AdminMiddleware::class]]);
$router->delete('/api/library/tv-shows/{id}', [MediaLibraryController::class, 'deleteTvShow'], ['middleware' => [AdminMiddleware::class]]);

// Books
$router->get('/api/library/books', [MediaLibraryController::class, 'listBooks']); // Public
$router->post('/api/library/books', [MediaLibraryController::class, 'addBook'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/library/books/{id}', [MediaLibraryController::class, 'getBook']); // Public
$router->put('/api/library/books/{id}', [MediaLibraryController::class, 'updateBook'], ['middleware' => [AdminMiddleware::class]]);
$router->delete('/api/library/books/{id}', [MediaLibraryController::class, 'deleteBook'], ['middleware' => [AdminMiddleware::class]]);

// Games
$router->get('/api/library/games', [MediaLibraryController::class, 'listGames']); // Public
$router->post('/api/library/games', [MediaLibraryController::class, 'addGame'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/library/games/{id}', [MediaLibraryController::class, 'getGame']); // Public
$router->put('/api/library/games/{id}', [MediaLibraryController::class, 'updateGame'], ['middleware' => [AdminMiddleware::class]]);
$router->delete('/api/library/games/{id}', [MediaLibraryController::class, 'deleteGame'], ['middleware' => [AdminMiddleware::class]]);

// Podcasts
$router->get('/api/library/podcasts', [MediaLibraryController::class, 'listPodcasts']); // Public
$router->post('/api/library/podcasts', [MediaLibraryController::class, 'addPodcast'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/library/podcasts/{id}', [MediaLibraryController::class, 'getPodcast']); // Public
$router->put('/api/library/podcasts/{id}', [MediaLibraryController::class, 'updatePodcast'], ['middleware' => [AdminMiddleware::class]]);
$router->delete('/api/library/podcasts/{id}', [MediaLibraryController::class, 'deletePodcast'], ['middleware' => [AdminMiddleware::class]]);

// Documentaries
$router->get('/api/library/documentaries', [MediaLibraryController::class, 'listDocumentaries']); // Public
$router->post('/api/library/documentaries', [MediaLibraryController::class, 'addDocumentary'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/library/documentaries/{id}', [MediaLibraryController::class, 'getDocumentary']); // Public
$router->put('/api/library/documentaries/{id}', [MediaLibraryController::class, 'updateDocumentary'], ['middleware' => [AdminMiddleware::class]]);
$router->delete('/api/library/documentaries/{id}', [MediaLibraryController::class, 'deleteDocumentary'], ['middleware' => [AdminMiddleware::class]]);

// Anime
$router->get('/api/library/anime', [MediaLibraryController::class, 'listAnime']); // Public
$router->post('/api/library/anime', [MediaLibraryController::class, 'addAnime'], ['middleware' => [AdminMiddleware::class]]);
$router->get('/api/library/anime/{id}', [MediaLibraryController::class, 'getAnime']); // Public
$router->put('/api/library/anime/{id}', [MediaLibraryController::class, 'updateAnime'], ['middleware' => [AdminMiddleware::class]]);
$router->patch('/api/library/anime/{id}/progress', [MediaLibraryController::class, 'updateAnimeProgress'], ['middleware' => [AdminMiddleware::class]]);
$router->delete('/api/library/anime/{id}', [MediaLibraryController::class, 'deleteAnime'], ['middleware' => [AdminMiddleware::class]]);

// Users
$router->get('/api/users/{email}', [UserController::class, 'show']); // User profile by email
$router->post('/api/users/me', [UserController::class, 'updateMe'], ['auth' => true]); // Update own profile (POST for file upload)

// User Settings (API Keys)
$router->get('/api/user/settings', [UserSettingsController::class, 'getSettings'], ['auth' => true]);
$router->post('/api/user/settings', [UserSettingsController::class, 'saveSettings'], ['auth' => true]);

// 執行
    $router->dispatch();
} catch (Throwable $e) {
    ExceptionHandler::handle($e);
}

?>
