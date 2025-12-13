<?php
require_once __DIR__ . '/_tool_guard.php';
/**
 * OPTIONS Request Test
 * This endpoint specifically tests OPTIONS preflight handling
 */

// Set CORS headers manually for testing
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

$allowedOrigins = [
    'https://pyq.3331322.xyz',
    'https://p-weibo-frontend.pages.dev'
];

if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Max-Age: 86400');
    header('Vary: Origin');
}

// Handle OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Handle GET/POST
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'CORS test endpoint',
    'method' => $_SERVER['REQUEST_METHOD'],
    'origin' => $origin,
    'headers_sent' => headers_list()
]);
