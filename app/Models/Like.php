<?php
namespace App\Models;

use App\Core\QueryBuilder;

class Like {
    public static function add(int $userId, int $postId): void {
        QueryBuilder::table('likes')->insertIgnore([
            'user_id' => $userId,
            'post_id' => $postId
        ]);
    }

    public static function countByPost(int $postId): int {
        return QueryBuilder::table('likes')
            ->where('post_id', '=', $postId)
            ->count();
    }
}
?>