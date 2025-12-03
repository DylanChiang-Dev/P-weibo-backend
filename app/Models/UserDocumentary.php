<?php
namespace App\Models;

use App\Core\QueryBuilder;

class UserDocumentary {
    public static function create(array $data): int {
        return QueryBuilder::table('user_documentaries')->insert($data);
    }
    
    public static function getById(int $id): ?array {
        return QueryBuilder::table('user_documentaries')
            ->where('id', '=', $id)
            ->first();
    }
    
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0): array {
        $query = QueryBuilder::table('user_documentaries')
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
    
    public static function count(int $userId, ?string $status = null): int {
        $query = QueryBuilder::table('user_documentaries')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        return $query->count();
    }
    
    public static function update(int $id, array $data): void {
        QueryBuilder::table('user_documentaries')
            ->where('id', '=', $id)
            ->update($data);
    }
    
    public static function delete(int $id): void {
        QueryBuilder::table('user_documentaries')
            ->where('id', '=', $id)
            ->delete();
    }
    
    public static function exists(int $userId, int $tmdbId): bool {
        $result = QueryBuilder::table('user_documentaries')
            ->where('user_id', '=', $userId)
            ->where('tmdb_id', '=', $tmdbId)
            ->first();
        
        return $result !== null;
    }
}
?>
