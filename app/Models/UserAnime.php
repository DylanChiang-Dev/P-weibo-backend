<?php
namespace App\Models;

use App\Core\QueryBuilder;

class UserAnime {
    public static function create(array $data): int {
        return QueryBuilder::table('user_anime')->insert($data);
    }
    
    public static function getById(int $id): ?array {
        return QueryBuilder::table('user_anime')
            ->where('id', '=', $id)
            ->first();
    }
    
    public static function list(int $userId, ?string $status = null, int $limit = 20, int $offset = 0): array {
        $query = QueryBuilder::table('user_anime')
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
        $query = QueryBuilder::table('user_anime')
            ->where('user_id', '=', $userId);
        
        if ($status) {
            $query->where('status', '=', $status);
        }
        
        return $query->count();
    }
    
    public static function update(int $id, array $data): void {
        QueryBuilder::table('user_anime')
            ->where('id', '=', $id)
            ->update($data);
    }
    
    public static function updateProgress(int $id, int $episodesWatched): void {
        QueryBuilder::table('user_anime')
            ->where('id', '=', $id)
            ->update(['episodes_watched' => $episodesWatched]);
    }
    
    public static function delete(int $id): void {
        QueryBuilder::table('user_anime')
            ->where('id', '=', $id)
            ->delete();
    }
    
    public static function exists(int $userId, int $animeId): bool {
        $result = QueryBuilder::table('user_anime')
            ->where('user_id', '=', $userId)
            ->where('anime_id', '=', $animeId)
            ->first();
        
        return $result !== null;
    }
}
?>
