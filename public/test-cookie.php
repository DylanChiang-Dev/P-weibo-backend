<?php
require_once __DIR__ . '/_tool_guard.php';
/**
 * Test Cookie Setting
 * This endpoint tests if cookies are being set correctly
 */

header('Content-Type: application/json; charset=utf-8');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = [
    'https://pyq.3331322.xyz',
    'https://p-weibo-frontend.pages.dev'
];

if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Set a test cookie
setcookie('test_cookie', 'test_value_' . time(), [
    'expires' => time() + 3600,
    'path' => '/',
    'domain' => '',  // Empty for same domain
    'secure' => true,  // HTTPS only
    'httponly' => true,
    'samesite' => 'None'  // Cross-site allowed
]);

// Set another test cookie with domain
setcookie('test_cookie_with_domain', 'test_value_domain_' . time(), [
    'expires' => time() + 3600,
    'path' => '/',
    'domain' => '.3331322.xyz',  // Allow subdomain sharing
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);

echo json_encode([
    'success' => true,
    'message' => 'Test cookies set',
    'origin' => $origin,
    'cookies_received' => $_COOKIE,
    'headers_sent' => headers_list(),
    'instructions' => [
        'Check Network tab → Response Headers for Set-Cookie',
        'Check Application tab → Cookies to see if they were stored',
        'Refresh this page to see if cookies were sent back in cookies_received'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
