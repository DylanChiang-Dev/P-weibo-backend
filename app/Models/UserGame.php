<?php
namespace App\Models;

use App\Core\QueryBuilder;

class UserGame {
    /**
     * Create a new game record
     */
    public static function create(array $data): int {
        return QueryBuilder::table('user_games')->insert($data);
    }
    
    /**
     * Get game by ID
     */
    public static function getById(int $id): ?array {
        return QueryBuilder::table('user_games')
            ->where('id', '=', $id)
            ->first();
    }
    
    /**
     * List games for a user
     */
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0): array {
        $query = QueryBuilder::table('user_games')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        return $query->orderBy('completed_date', 'DESC')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }
    
    /**
     * Count total games for a user
     */
    public static function count(int $userId, ?string $status = null): int {
        $query = QueryBuilder::table('user_games')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        return $query->count();
    }
    
    /**
     * Update game record
     */
    public static function update(int $id, array $data): void {
        QueryBuilder::table('user_games')
            ->where('id', '=', $id)
            ->update($data);
    }
    
    /**
     * Delete game record
     */
    public static function delete(int $id): void {
        QueryBuilder::table('user_games')
            ->where('id', '=', $id)
            ->delete();
    }
    
    /**
     * Check if user already has this game by RAWG ID
     */
    public static function exists(int $userId, int $rawgId): bool {
        $result = QueryBuilder::table('user_games')
            ->where('user_id', '=', $userId)
            ->where('rawg_id', '=', $rawgId)
            ->first();
        
        return $result !== null;
    }
    
    /**
     * Check if user already has this game by IGDB ID
     */
    public static function existsByIgdbId(int $userId, int $igdbId): bool {
        $result = QueryBuilder::table('user_games')
            ->where('user_id', '=', $userId)
            ->where('igdb_id', '=', $igdbId)
            ->first();
        
        return $result !== null;
    }
}
?>
