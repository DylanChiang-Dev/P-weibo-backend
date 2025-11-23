<?php
/**
 * CORS Configuration Test Tool
 * This script helps diagnose CORS issues
 */

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__);
$envPath = $root . '/.env';

$response = [
    'success' => true,
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 1: Check if .env exists
$response['tests']['env_file'] = [
    'name' => '.env 文件檢查',
    'exists' => file_exists($envPath),
    'path' => $envPath
];

// Test 2: Load config and check FRONTEND_ORIGIN
try {
    require_once $root . '/config/config.php';
    $config = config();
    
    $frontendOrigin = $config['frontend_origin'] ?? null;
    $response['tests']['frontend_origin'] = [
        'name' => 'FRONTEND_ORIGIN 配置',
        'value' => $frontendOrigin,
        'allowed_origins' => $frontendOrigin ? array_map('trim', explode(',', $frontendOrigin)) : []
    ];
} catch (Exception $e) {
    $response['success'] = false;
    $response['tests']['frontend_origin'] = [
        'name' => 'FRONTEND_ORIGIN 配置',
        'error' => $e->getMessage()
    ];
}

// Test 3: Check current request origin
$currentOrigin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? 'none';
$response['tests']['current_request'] = [
    'name' => '當前請求來源',
    'origin' => $currentOrigin,
    'host' => $_SERVER['HTTP_HOST'] ?? 'unknown'
];

// Test 4: Simulate CORS check
if (isset($frontendOrigin) && $currentOrigin !== 'none') {
    $allowedOrigins = array_map('trim', explode(',', $frontendOrigin));
    $isAllowed = in_array($currentOrigin, $allowedOrigins) || in_array('*', $allowedOrigins);
    
    $response['tests']['cors_check'] = [
        'name' => 'CORS 驗證',
        'is_allowed' => $isAllowed,
        'would_send_header' => $isAllowed ? "Access-Control-Allow-Origin: $currentOrigin" : 'none'
    ];
    
    // Actually send CORS headers for testing
    if ($isAllowed) {
        header("Access-Control-Allow-Origin: $currentOrigin");
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
}

// Test 5: Check CorsMiddleware file
$corsMiddlewarePath = $root . '/app/Middleware/CorsMiddleware.php';
$response['tests']['cors_middleware'] = [
    'name' => 'CorsMiddleware 文件',
    'exists' => file_exists($corsMiddlewarePath),
    'path' => $corsMiddlewarePath,
    'last_modified' => file_exists($corsMiddlewarePath) ? date('Y-m-d H:i:s', filemtime($corsMiddlewarePath)) : null
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
