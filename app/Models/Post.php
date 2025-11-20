<?php
namespace App\Models;

use App\Core\QueryBuilder;

class Post {
    public static function create(int $userId, string $content): int {
        return QueryBuilder::table('posts')->insert([
            'user_id' => $userId,
            'content' => $content
        ]);
    }

    public static function getById(int $id): ?array {
        return QueryBuilder::table('posts')
            ->select(['posts.*', 'users.email', 'users.display_name', 'users.avatar_path'])
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->where('posts.id', '=', $id)
            ->where('posts.is_deleted', '=', 0)
            ->first();
    }

    public static function list(int $limit, ?string $cursor): array {
        $query = QueryBuilder::table('posts')
            ->select(['posts.*', 'users.email', 'users.display_name', 'users.avatar_path'])
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->where('posts.is_deleted', '=', 0)
            ->orderBy('posts.is_pinned', 'DESC')
            ->orderBy('posts.created_at', 'DESC')
            ->orderBy('posts.id', 'DESC')
            ->limit($limit);

        if ($cursor) {
            $decoded = base64_decode($cursor, true);
            if ($decoded) {
                [$c_at, $c_id] = explode('|', $decoded);
                $query->whereRaw('(posts.created_at < ? OR (posts.created_at = ? AND posts.id < ?))', [$c_at, $c_at, (int)$c_id]);
            }
        }

        return $query->get();
    }

    public static function pin(int $id): void {
        QueryBuilder::table('posts')
            ->where('id', '=', $id)
            ->update(['is_pinned' => 1]);
    }

    public static function unpin(int $id): void {
        QueryBuilder::table('posts')
            ->where('id', '=', $id)
            ->update(['is_pinned' => 0]);
    }

    public static function softDelete(int $id): void {
        QueryBuilder::table('posts')
            ->where('id', '=', $id)
            ->update(['is_deleted' => 1]);
    }
}
?>