<?php
namespace App\Models;

use App\Core\QueryBuilder;

class UserMovie {
    /**
     * Create a new movie record
     */
    public static function create(array $data): int {
        return QueryBuilder::table('user_movies')->insert($data);
    }
    
    /**
     * Get movie by ID
     */
    public static function getById(int $id): ?array {
        return QueryBuilder::table('user_movies')
            ->where('id', '=', $id)
            ->first();
    }
    
    /**
     * List movies for a user
     */
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0): array {
        $query = QueryBuilder::table('user_movies')
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
     * Count total movies for a user
     */
    public static function count(int $userId, ?string $status = null): int {
        $query = QueryBuilder::table('user_movies')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        return $query->count();
    }
    
    /**
     * Update movie record
     */
    public static function update(int $id, array $data): void {
        QueryBuilder::table('user_movies')
            ->where('id', '=', $id)
            ->update($data);
    }
    
    /**
     * Delete movie record
     */
    public static function delete(int $id): void {
        QueryBuilder::table('user_movies')
            ->where('id', '=', $id)
            ->delete();
    }
    
    /**
     * Check if user already has this movie
     */
    public static function exists(int $userId, int $tmdbId): bool {
        $result = QueryBuilder::table('user_movies')
            ->where('user_id', '=', $userId)
            ->where('tmdb_id', '=', $tmdbId)
            ->first();
        
        return $result !== null;
    }
}
?>
