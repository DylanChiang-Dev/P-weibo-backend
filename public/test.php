<?php
// Simple test endpoint - no dependencies
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'PHP is working!',
    'php_version' => phpversion(),
    'timestamp' => date('Y-m-d H:i:s')
]);
