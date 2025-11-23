<?php
/**
 * Fix CORS Configuration Script
 * Updates .env FRONTEND_ORIGIN to allow the frontend domain
 */

header('Content-Type: application/json');

$root = dirname(__DIR__);
$envPath = $root . '/.env';

if (!file_exists($envPath)) {
    echo json_encode(['success' => false, 'error' => '.env file not found']);
    exit;
}

$frontendDomain = 'https://pyq.3331322.xyz';

try {
    $envContent = file_get_contents($envPath);
    
    // Check if FRONTEND_ORIGIN exists
    if (preg_match('/^FRONTEND_ORIGIN=(.*)$/m', $envContent, $matches)) {
        $currentOrigin = trim($matches[1]);
        
        // If already correct, do nothing
        if ($currentOrigin === $frontendDomain) {
            echo json_encode(['success' => true, 'message' => 'CORS configuration is already correct.']);
            exit;
        }
        
        // Update it
        $newContent = preg_replace(
            '/^FRONTEND_ORIGIN=.*$/m', 
            "FRONTEND_ORIGIN=$frontendDomain", 
            $envContent
        );
    } else {
        // Append it
        $newContent = $envContent . "\nFRONTEND_ORIGIN=$frontendDomain\n";
    }
    
    if (file_put_contents($envPath, $newContent) === false) {
        throw new Exception('Failed to write to .env file');
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'CORS configuration updated successfully!',
        'old_origin' => $currentOrigin ?? 'none',
        'new_origin' => $frontendDomain
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
