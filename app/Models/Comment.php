<?php
namespace App\Models;

use App\Core\QueryBuilder;

class Comment {
    public static function add(?int $userId, int $postId, string $content, ?string $authorName = null): int {
        $data = [
            'post_id' => $postId,
            'content' => $content
        ];
        if ($userId) {
            $data['user_id'] = $userId;
        }
        if ($authorName) {
            $data['author_name'] = $authorName;
        }
        return QueryBuilder::table('comments')->insert($data);
    }

    public static function countByPost(int $postId): int {
        return QueryBuilder::table('comments')
            ->where('post_id', '=', $postId)
            ->count();
    }

    public static function listByPost(int $postId): array {
        return QueryBuilder::table('comments')
            ->select(['comments.*', 'users.email', 'users.display_name', 'users.avatar_path'])
            ->leftJoin('users', 'comments.user_id', '=', 'users.id')
            ->where('comments.post_id', '=', $postId)
            ->orderBy('comments.created_at', 'ASC')
            ->get();
    }

    public static function getById(int $id): ?array {
        return QueryBuilder::table('comments')
            ->where('id', '=', $id)
            ->first();
    }

    public static function delete(int $id): void {
        QueryBuilder::table('comments')
            ->where('id', '=', $id)
            ->delete();
    }
}
?>