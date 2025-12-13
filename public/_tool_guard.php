<?php
declare(strict_types=1);

// Production safety: block direct access to internal maintenance/debug scripts in /public.
// This file is intended to be included at the top of those scripts.

$root = dirname(__DIR__);
$envPath = $root . '/.env';

// Allow installer/debug tooling when the server isn't configured yet.
if (!file_exists($envPath)) {
    return;
}

require_once $root . '/config/config.php';
$config = config();

if (($config['app_env'] ?? 'development') === 'production') {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => false, 'error' => 'Not Found'], JSON_UNESCAPED_UNICODE);
    exit;
}

