<?php
namespace App\Models;

use App\Core\QueryBuilder;

class BlogArticleLike {
    /**
     * Add a like (toggle)
     */
    public static function toggle(int $articleId, ?int $userId, string $ipAddress): bool {
        // Check if already liked
        $existing = self::find($articleId, $userId, $ipAddress);
        
        if ($existing) {
            // Unlike
            self::delete($articleId, $userId, $ipAddress);
            return false;
        } else {
            // Like
            QueryBuilder::table('blog_article_likes')->insert([
                'article_id' => $articleId,
                'user_id' => $userId,
                'ip_address' => $userId ? null : $ipAddress // Only store IP for guests
            ]);
            return true;
        }
    }

    /**
     * Check if liked
     */
    public static function isLiked(int $articleId, ?int $userId, string $ipAddress): bool {
        return self::find($articleId, $userId, $ipAddress) !== null;
    }

    /**
     * Find like record
     */
    private static function find(int $articleId, ?int $userId, string $ipAddress): ?array {
        $query = QueryBuilder::table('blog_article_likes')
            ->where('article_id', '=', $articleId);

        if ($userId) {
            $query->where('user_id', '=', $userId);
        } else {
            $query->where('ip_address', '=', $ipAddress);
        }

        return $query->first();
    }

    /**
     * Delete like
     */
    private static function delete(int $articleId, ?int $userId, string $ipAddress): void {
        $query = QueryBuilder::table('blog_article_likes')
            ->where('article_id', '=', $articleId);

        if ($userId) {
            $query->where('user_id', '=', $userId);
        } else {
            $query->where('ip_address', '=', $ipAddress);
        }

        $query->delete();
    }

    /**
     * Count likes for an article
     */
    public static function count(int $articleId): int {
        return QueryBuilder::table('blog_article_likes')
            ->where('article_id', '=', $articleId)
            ->count();
    }
}
?>
