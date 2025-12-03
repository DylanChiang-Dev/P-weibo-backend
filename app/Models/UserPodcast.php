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
    
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0): array {
        $query = QueryBuilder::table('user_podcasts')
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
        $query = QueryBuilder::table('user_podcasts')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
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
}
?>
