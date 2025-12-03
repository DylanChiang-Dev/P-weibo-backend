<?php
namespace App\Models;

use App\Core\QueryBuilder;

class UserSettings {
    /**
     * Get user settings by user ID
     */
    public static function getByUserId(int $userId): ?array {
        return QueryBuilder::table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();
    }
    
    /**
     * Create or update user settings
     */
    public static function upsert(int $userId, array $data): void {
        $existing = self::getByUserId($userId);
        
        if ($existing) {
            // Update existing settings
            QueryBuilder::table('user_settings')
                ->where('user_id', '=', $userId)
                ->update($data);
        } else {
            // Create new settings
            $data['user_id'] = $userId;
            QueryBuilder::table('user_settings')->insert($data);
        }
    }
    
    /**
     * Get API keys for a user
     */
    public static function getApiKeys(int $userId): array {
        $settings = self::getByUserId($userId);
        
        if (!$settings || empty($settings['api_keys'])) {
            return [
                'tmdb_api_key' => '',
                'rawg_api_key' => '',
                'google_books_api_key' => ''
            ];
        }
        
        $apiKeys = json_decode($settings['api_keys'], true);
        
        // Ensure all keys exist with default empty values
        return [
            'tmdb_api_key' => $apiKeys['tmdb_api_key'] ?? '',
            'rawg_api_key' => $apiKeys['rawg_api_key'] ?? '',
            'google_books_api_key' => $apiKeys['google_books_api_key'] ?? ''
        ];
    }
    
    /**
     * Save API keys for a user
     */
    public static function saveApiKeys(int $userId, array $apiKeys): void {
        $data = [
            'api_keys' => json_encode($apiKeys)
        ];
        
        self::upsert($userId, $data);
    }
}
?>
