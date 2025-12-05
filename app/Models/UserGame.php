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
     * List games for a user with search and sort support
     */
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0, ?string $search = null, string $sort = 'date_desc'): array {
        $query = QueryBuilder::table('user_games')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        // Search in name and my_review fields (case-insensitive)
        if ($search) {
            $searchTerm = '%' . strtolower($search) . '%';
            $query->whereRaw('(LOWER(name) LIKE ? OR LOWER(my_review) LIKE ?)', [$searchTerm, $searchTerm]);
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
     * Count total games for a user
     */
    public static function count(int $userId, ?string $status = null, ?string $search = null): int {
        $query = QueryBuilder::table('user_games')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        // Search in name and my_review fields (case-insensitive)
        if ($search) {
            $searchTerm = '%' . strtolower($search) . '%';
            $query->whereRaw('(LOWER(name) LIKE ? OR LOWER(my_review) LIKE ?)', [$searchTerm, $searchTerm]);
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
