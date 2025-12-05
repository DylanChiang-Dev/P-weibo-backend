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
     * List movies for a user with search and sort support
     */
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0, ?string $search = null, string $sort = 'date_desc'): array {
        $query = QueryBuilder::table('user_movies')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        // Search in title and my_review fields (case-insensitive)
        if ($search) {
            $searchTerm = '%' . strtolower($search) . '%';
            $query->whereRaw('(LOWER(title) LIKE ? OR LOWER(my_review) LIKE ?)', [$searchTerm, $searchTerm]);
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
                // NULLS LAST: use COALESCE to put nulls at the end
                $query->orderBy('COALESCE(completed_date, \'1900-01-01\')', 'DESC')
                    ->orderBy('created_at', 'DESC');
                break;
            case 'completed_asc':
            case 'completed_date_asc':  // 别名
                // NULLS LAST: nulls sort after all valid dates
                $query->orderBy('CASE WHEN completed_date IS NULL THEN 1 ELSE 0 END', 'ASC')
                    ->orderBy('completed_date', 'ASC');
                break;
            case 'rating_desc':
                // NULLS LAST: use COALESCE to put nulls at the end
                $query->orderBy('COALESCE(my_rating, 0)', 'DESC')
                    ->orderBy('created_at', 'DESC');
                break;
            case 'rating_asc':
                // NULLS LAST: nulls sort after all valid ratings
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
     * Count total movies for a user
     */
    public static function count(int $userId, ?string $status = null, ?string $search = null): int {
        $query = QueryBuilder::table('user_movies')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        // Search in title and my_review fields (case-insensitive)
        if ($search) {
            $searchTerm = '%' . strtolower($search) . '%';
            $query->whereRaw('(LOWER(title) LIKE ? OR LOWER(my_review) LIKE ?)', [$searchTerm, $searchTerm]);
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
