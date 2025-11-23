<?php
namespace App\Models;

use App\Core\QueryBuilder;

class Post {
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PRIVATE = 'private';

    public static function create(int $userId, string $content, string $visibility = self::VISIBILITY_PUBLIC): int {
        return QueryBuilder::table('posts')->insert([
            'user_id' => $userId,
            'content' => $content,
            'visibility' => $visibility
        ]);
    }

    public static function getById(int $id): ?array {
        return QueryBuilder::table('posts')
            ->select([
                'posts.*', 
                'posts.visibility',
                'users.email', 
                'users.display_name', 
                'users.avatar_path',
                'users.role'
            ])
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->where('posts.id', '=', $id)
            ->where('posts.is_deleted', '=', 0)
            ->first();
    }

    public static function list(int $limit, ?string $cursor, ?int $currentUserId = null): array {
        $query = QueryBuilder::table('posts')
            ->select(['posts.*', 'users.email', 'users.display_name', 'users.avatar_path', 'users.role'])
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->where('posts.is_deleted', '=', 0);

        // Visibility filter: show public posts + own private posts
        if ($currentUserId) {
            $query->whereRaw('(posts.visibility = ? OR (posts.visibility = ? AND posts.user_id = ?))', 
                [self::VISIBILITY_PUBLIC, self::VISIBILITY_PRIVATE, $currentUserId]);
        } else {
            // Not logged in: only show public posts
            $query->where('posts.visibility', '=', self::VISIBILITY_PUBLIC);
        }

        $query->orderBy('posts.is_pinned', 'DESC')
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

    public static function update(int $id, array $data): void {
        QueryBuilder::table('posts')
            ->where('id', '=', $id)
            ->update($data);
    }
}
?>