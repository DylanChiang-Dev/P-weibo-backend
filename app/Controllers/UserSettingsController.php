<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Core\Validator;
use App\Exceptions\ValidationException;
use App\Models\UserSettings;

class UserSettingsController {
    /**
     * Get user settings
     */
    public function getSettings(Request $req): void {
        $userId = (int)$req->user['id'];
        
        $apiKeys = UserSettings::getApiKeys($userId);
        
        ApiResponse::success([
            'api_keys' => $apiKeys
        ]);
    }
    
    /**
     * Save/update user settings
     */
    public function saveSettings(Request $req): void {
        $userId = (int)$req->user['id'];
        $data = is_array($req->body) ? $req->body : [];
        
        // Validate request
        if (!isset($data['api_keys']) || !is_array($data['api_keys'])) {
            throw new ValidationException('Invalid request: api_keys must be an object');
        }
        
        $apiKeys = $data['api_keys'];
        
        // Optional: Validate API key formats
        $allowedKeys = ['tmdb_api_key', 'rawg_api_key', 'google_books_api_key'];
        $filteredKeys = [];
        
        foreach ($allowedKeys as $key) {
            if (isset($apiKeys[$key])) {
                $filteredKeys[$key] = trim($apiKeys[$key]);
            }
        }
        
        // Save to database
        UserSettings::saveApiKeys($userId, $filteredKeys);
        
        ApiResponse::success([
            'message' => 'Settings saved successfully'
        ]);
    }
}
?>
