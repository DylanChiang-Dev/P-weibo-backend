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
     * List TV shows for a user with search and sort support
     */
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0, ?string $search = null, string $sort = 'date_desc'): array {
        $query = QueryBuilder::table('user_tv_shows')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        // Search in my_review field (case-insensitive)
        if ($search) {
            $query->whereRaw('LOWER(my_review) LIKE ?', ['%' . strtolower($search) . '%']);
        }
        
        // Apply sorting
        self::applySort($query, $sort);
        
        return $query->limit($limit)
            ->offset($offset)
            ->get();
    }
    
    /**
     * Apply sorting to query
     */
    private static function applySort(QueryBuilder $query, string $sort): void {
        switch ($sort) {
            case 'date_asc':
                $query->orderBy('created_at', 'ASC');
                break;
            case 'completed_desc':
            case 'completed_date_desc':  // 别名
                $query->orderBy('COALESCE(completed_date, \'1900-01-01\')', 'DESC')
                    ->orderBy('created_at', 'DESC');
                break;
            case 'completed_asc':
            case 'completed_date_asc':  // 别名
                $query->orderBy('CASE WHEN completed_date IS NULL THEN 1 ELSE 0 END', 'ASC')
                    ->orderBy('completed_date', 'ASC');
                break;
            case 'rating_desc':
                $query->orderBy('COALESCE(my_rating, 0)', 'DESC')
                    ->orderBy('created_at', 'DESC');
                break;
            case 'rating_asc':
                $query->orderBy('CASE WHEN my_rating IS NULL THEN 1 ELSE 0 END', 'ASC')
                    ->orderBy('my_rating', 'ASC');
                break;
            case 'date_desc':
            default:
                $query->orderBy('created_at', 'DESC');
                break;
        }
    }
    
    /**
     * Count total TV shows for a user
     */
    public static function count(int $userId, ?string $status = null, ?string $search = null): int {
        $query = QueryBuilder::table('user_tv_shows')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        // Search in my_review field (case-insensitive)
        if ($search) {
            $query->whereRaw('LOWER(my_review) LIKE ?', ['%' . strtolower($search) . '%']);
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
