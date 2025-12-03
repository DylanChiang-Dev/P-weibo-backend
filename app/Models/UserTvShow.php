<?php
namespace App\Models;

use App\Core\QueryBuilder;

class UserTvShow {
    /**
     * Create a new TV show record
     */
    public static function create(array $data): int {
        return QueryBuilder::table('user_tv_shows')->insert($data);
    }
    
    /**
     * Get TV show by ID
     */
    public static function getById(int $id): ?array {
        return QueryBuilder::table('user_tv_shows')
            ->where('id', '=', $id)
            ->first();
    }
    
    /**
     * List TV shows for a user
     */
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0): array {
        $query = QueryBuilder::table('user_tv_shows')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        return $query->orderBy('updated_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
    }
    
    /**
     * Count total TV shows for a user
     */
    public static function count(int $userId, ?string $status = null): int {
        $query = QueryBuilder::table('user_tv_shows')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        return $query->count();
    }
    
    /**
     * Update TV show record
     */
    public static function update(int $id, array $data): void {
        QueryBuilder::table('user_tv_shows')
            ->where('id', '=', $id)
            ->update($data);
    }
    
    /**
     * Update progress (season/episode)
     */
    public static function updateProgress(int $id, int $season, int $episode): void {
        QueryBuilder::table('user_tv_shows')
            ->where('id', '=', $id)
            ->update([
                'current_season' => $season,
                'current_episode' => $episode
            ]);
    }
    
    /**
     * Delete TV show record
     */
    public static function delete(int $id): void {
        QueryBuilder::table('user_tv_shows')
            ->where('id', '=', $id)
            ->delete();
    }
    
    /**
     * Check if user already has this TV show
     */
    public static function exists(int $userId, int $tmdbId): bool {
        $result = QueryBuilder::table('user_tv_shows')
            ->where('user_id', '=', $userId)
            ->where('tmdb_id', '=', $tmdbId)
            ->first();
        
        return $result !== null;
    }
}
?>
