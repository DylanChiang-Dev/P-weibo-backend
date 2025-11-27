<?php
namespace App\Models;

use App\Core\QueryBuilder;

class BlogComment {
    /**
     * Create a new comment
     */
    public static function create(array $data): int {
        return QueryBuilder::table('blog_comments')->insert($data);
    }

    /**
     * Get comment by ID
     */
    public static function getById(int $id): ?array {
        return QueryBuilder::table('blog_comments')
            ->where('id', '=', $id)
            ->first();
    }

    /**
     * Get comments for an article
     */
    public static function getByArticle(int $articleId, string $status = 'approved'): array {
        $query = QueryBuilder::table('blog_comments')
            ->select(['blog_comments.*', 'users.email', 'users.display_name', 'users.avatar_path'])
            ->leftJoin('users', 'blog_comments.user_id', '=', 'users.id')
            ->where('blog_comments.article_id', '=', $articleId);

        if ($status !== 'all') {
            $query->where('blog_comments.status', '=', $status);
        }

        return $query->orderBy('blog_comments.created_at', 'ASC')->get();
    }

    /**
     * Count comments by article
     */
    public static function countByArticle(int $articleId, string $status = 'approved'): int {
        $query = QueryBuilder::table('blog_comments')
            ->where('article_id', '=', $articleId);

        if ($status !== 'all') {
            $query->where('status', '=', $status);
        }

        return $query->count();
    }

    /**
     * Update comment status
     */
    public static function updateStatus(int $id, string $status): void {
        QueryBuilder::table('blog_comments')
            ->where('id', '=', $id)
            ->update(['status' => $status]);
    }

    /**
     * Delete comment
     */
    public static function delete(int $id): void {
        QueryBuilder::table('blog_comments')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Get pending comments (for moderation)
     */
    public static function getPending(int $limit = 50): array {
        return QueryBuilder::table('blog_comments')
            ->select(['blog_comments.*', 'blog_articles.title as article_title'])
            ->join('blog_articles', 'blog_comments.article_id', '=', 'blog_articles.id')
            ->where('blog_comments.status', '=', 'pending')
            ->orderBy('blog_comments.created_at', 'DESC')
            ->limit($limit)
            ->get();
    }
}
?>
