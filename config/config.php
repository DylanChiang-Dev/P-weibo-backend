<?php
// 設定載入器：讀取 .env 並提供設定陣列

function load_env(string $path): array {
    $env = [];
    if (!file_exists($path)) {
        return $env;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        // 去除包覆引號
        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) || (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
            $val = substr($val, 1, -1);
        }
        $env[$key] = $val;
    }
    return $env;
}

function config(): array {
    static $config = null;
    if ($config !== null) return $config;

    $root = dirname(__DIR__);
    $env = load_env($root . '/.env');

    $get = function ($key, $default = null) use ($env) {
        return $env[$key] ?? $default;
    };

    $frontendOriginRaw = $get('FRONTEND_ORIGIN', 'http://localhost:3000');
    $frontendOrigins = array_values(array_filter(array_map('trim', explode(',', $frontendOriginRaw)), fn ($v) => $v !== ''));

    // Always allow known production frontends to avoid manual server edits on deploy.
    $alwaysAllowedOrigins = [
        'https://p-blog-frontend.pages.dev',
        'https://pyq.3331322.xyz',
        'https://p-memory-lane.pages.dev',
    ];

    if (!in_array('*', $frontendOrigins, true)) {
        foreach ($alwaysAllowedOrigins as $origin) {
            if (!in_array($origin, $frontendOrigins, true)) {
                $frontendOrigins[] = $origin;
            }
        }
    }

    $config = [
        'app_env' => $get('APP_ENV', 'development'),
        'app_url' => $get('APP_URL', 'http://localhost'),
        'frontend_origin' => implode(',', $frontendOrigins),
        'cookie' => [
            // If you need a shared cookie across subdomains, set COOKIE_DOMAIN=.3331322.xyz
            'domain' => $get('COOKIE_DOMAIN', ''),
            // For cross-site XHR (e.g. pages.dev), this must be None + Secure.
            'samesite' => $get('COOKIE_SAMESITE', 'None'),
            'secure' => (bool)(($get('COOKIE_SECURE')) ?? ($get('APP_ENV', 'development') !== 'development')),
        ],
        'redis' => [
            'enabled' => (bool)$get('REDIS_ENABLED', false),
            'host' => $get('REDIS_HOST', '127.0.0.1'),
            'port' => (int)$get('REDIS_PORT', 6379),
            'password' => $get('REDIS_PASSWORD', ''),
            'db' => (int)$get('REDIS_DB', 0),
            'prefix' => $get('REDIS_PREFIX', 'pweibo:'),
            // Short timeouts to avoid hanging request path
            'timeout' => (float)$get('REDIS_TIMEOUT', 0.2),
            'persistent' => (bool)$get('REDIS_PERSISTENT', true),
        ],
        'db' => [
            'host' => $get('DB_HOST', '127.0.0.1'),
            'port' => (int)$get('DB_PORT', 3306),
            'name' => $get('DB_NAME', 'weibo_clone'),
            'user' => $get('DB_USER', 'root'),
            'pass' => $get('DB_PASS', ''),
            'charset' => $get('DB_CHARSET', 'utf8mb4'),
        ],
        'jwt' => [
            'access_secret' => $get('JWT_ACCESS_SECRET', ''),
            'refresh_secret' => $get('JWT_REFRESH_SECRET', ''),
            'access_ttl' => (int)$get('JWT_ACCESS_TTL', 900),
            'refresh_ttl' => (int)$get('JWT_REFRESH_TTL', 1209600),
        ],
        'upload' => [
            'path' => $get('UPLOAD_PATH', dirname(__DIR__) . '/public/uploads'),
            'max_mb' => (int)$get('MAX_UPLOAD_MB', 10), // Legacy, for backward compatibility
            'max_image_mb' => (int)$get('MAX_IMAGE_MB', 10),
            'max_video_mb' => (int)$get('MAX_VIDEO_MB', 100),
            // Resource protection (safe defaults; override via .env if needed)
            'max_files_per_request' => (int)$get('MAX_FILES_PER_REQUEST', 20),
            'max_total_upload_mb' => (int)$get('MAX_TOTAL_UPLOAD_MB', 150),
            'max_images_per_post' => (int)$get('MAX_IMAGES_PER_POST', 9),
            'max_videos_per_post' => (int)$get('MAX_VIDEOS_PER_POST', 1),
            'ffmpeg_timeout_seconds' => (int)$get('FFMPEG_TIMEOUT_SECONDS', 5),
        ],
        'log' => [
            'path' => $get('LOG_PATH', dirname(__DIR__) . '/logs'),
        ],
        'admin' => [
            'email' => $get('ADMIN_EMAIL'),
            'password' => $get('ADMIN_PASSWORD'),
            'display_name' => $get('ADMIN_DISPLAY_NAME', 'Admin'),
        ],
    ];

    return $config;
}

?>
