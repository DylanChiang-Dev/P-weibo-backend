<?php
namespace App\Models;

use App\Core\QueryBuilder;

class UserPodcast {
    public static function create(array $data): int {
        return QueryBuilder::table('user_podcasts')->insert($data);
    }
    
    public static function getById(int $id): ?array {
        return QueryBuilder::table('user_podcasts')
            ->where('id', '=', $id)
            ->first();
    }
    
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0, ?string $search = null, string $sort = 'date_desc'): array {
        $query = QueryBuilder::table('user_podcasts')
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
                $query->orderBy('COALESCE(completed_date, \'1900-01-01\')', 'DESC')
                    ->orderBy('created_at', 'DESC');
                break;
            case 'completed_asc':
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
    
    public static function count(int $userId, ?string $status = null, ?string $search = null): int {
        $query = QueryBuilder::table('user_podcasts')
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
    
    public static function update(int $id, array $data): void {
        QueryBuilder::table('user_podcasts')
            ->where('id', '=', $id)
            ->update($data);
    }
    
    public static function delete(int $id): void {
        QueryBuilder::table('user_podcasts')
            ->where('id', '=', $id)
            ->delete();
    }
    
    public static function exists(int $userId, string $podcastId): bool {
        $result = QueryBuilder::table('user_podcasts')
            ->where('user_id', '=', $userId)
            ->where('podcast_id', '=', $podcastId)
            ->first();
        
        return $result !== null;
    }
    
    public static function existsByItunesId(int $userId, int $itunesId): bool {
        $result = QueryBuilder::table('user_podcasts')
            ->where('user_id', '=', $userId)
            ->where('itunes_id', '=', $itunesId)
            ->first();
        
        return $result !== null;
    }
}
?>
