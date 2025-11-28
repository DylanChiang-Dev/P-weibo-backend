<?php
namespace App\Models;

use App\Core\QueryBuilder;

class Media {
    /**
     * Create a new media record
     */
    public static function create(array $data): int {
        return QueryBuilder::table('media')->insert($data);
    }

    /**
     * Get media by ID
     */
    public static function getById(int $id): ?array {
        return QueryBuilder::table('media')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * List user's media with pagination
     */
    public static function listByUser(
        int $userId, 
        int $limit = 50, 
        int $offset = 0
    ): array {
        return QueryBuilder::table('media')
            ->where('user_id', '=', $userId)
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }

    /**
     * Count user's media
     */
    public static function countByUser(int $userId): int {
        return QueryBuilder::table('media')
            ->where('user_id', '=', $userId)
            ->count();
    }

    /**
     * Delete media by ID
     */
    public static function delete(int $id): void {
        QueryBuilder::table('media')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Check if user owns the media
     */
    public static function isOwnedByUser(int $id, int $userId): bool {
        $media = QueryBuilder::table('media')
            ->where('id', '=', $id)
            ->where('user_id', '=', $userId)
            ->first();
        
        return $media !== null;
    }
}
?>
