<?php
namespace App\Models;

use App\Core\QueryBuilder;

class BlogArticle {
    /**
     * Create a new blog article
     */
    public static function create(array $data): int {
        return QueryBuilder::table('blog_articles')->insert($data);
    }

    /**
     * Get article by slug
     */
    public static function getBySlug(string $slug): ?array {
        return QueryBuilder::table('blog_articles')
            ->select(['blog_articles.*', 'users.email', 'users.display_name', 'users.avatar_path'])
            ->leftJoin('users', 'blog_articles.user_id', '=', 'users.id')
            ->where('blog_articles.slug', '=', $slug)
            ->first();
    }

    /**
     * Get article by ID
     */
    public static function getById(int $id): ?array {
        return QueryBuilder::table('blog_articles')
            ->select(['blog_articles.*', 'users.email', 'users.display_name', 'users.avatar_path'])
            ->leftJoin('users', 'blog_articles.user_id', '=', 'users.id')
            ->where('blog_articles.id', '=', $id)
            ->first();
    }

    /**
     * List articles with pagination
     */
    public static function list(int $limit = 20, ?string $cursor = null, string $status = 'published'): array {
        $query = QueryBuilder::table('blog_articles')
            ->select(['blog_articles.*', 'users.email', 'users.display_name', 'users.avatar_path'])
            ->leftJoin('users', 'blog_articles.user_id', '=', 'users.id')
            ->where('blog_articles.status', '=', $status);

        if ($cursor) {
            $decoded = base64_decode($cursor);
            [$createdAt, $id] = explode('|', $decoded);
            $query->where('blog_articles.created_at', '<=', $createdAt);
            $query->where('blog_articles.id', '<', $id);
        }

        return $query->orderBy('blog_articles.created_at', 'DESC')
            ->orderBy('blog_articles.id', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Update article
     */
    public static function update(int $id, array $data): void {
        QueryBuilder::table('blog_articles')
            ->where('id', '=', $id)
            ->update($data);
    }

    /**
     * Delete article
     */
    public static function delete(int $id): void {
        QueryBuilder::table('blog_articles')
            ->where('id', '=', $id)
            ->delete();
    }

    /**
     * Check if slug exists
     */
    public static function existsBySlug(string $slug): bool {
        $result = QueryBuilder::table('blog_articles')
            ->where('slug', '=', $slug)
            ->first();
        return $result !== null;
    }

    /**
     * Count articles by status
     */
    public static function count(string $status = 'published'): int {
        return QueryBuilder::table('blog_articles')
            ->where('status', '=', $status)
            ->count();
    }

    /**
     * List articles with page-based pagination
     */
    public static function listPaginated(
        int $limit = 20, 
        int $offset = 0, 
        string $status = 'published',
        string $orderBy = 'published_at',
        string $orderDir = 'DESC'
    ): array {
        $allowedOrderBy = ['published_at', 'created_at', 'view_count', 'id', 'title'];
        if (!in_array($orderBy, $allowedOrderBy)) {
            $orderBy = 'published_at';
        }
        
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        
        return QueryBuilder::table('blog_articles')
            ->select(['blog_articles.*', 'users.email', 'users.display_name', 'users.avatar_path'])
            ->leftJoin('users', 'blog_articles.user_id', '=', 'users.id')
            ->where('blog_articles.status', '=', $status)
            ->orderBy('blog_articles.' . $orderBy, $orderDir)
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * Increment view count
     */
    public static function incrementViewCount(int $id): void {
        QueryBuilder::table('blog_articles')
            ->where('id', '=', $id)
            ->increment('view_count');
    }

    /**
     * Get categories for an article
     */
    public static function getCategories(int $articleId): array {
        return QueryBuilder::table('blog_categories')
            ->select(['blog_categories.*'])
            ->join('blog_article_categories', 'blog_categories.id', '=', 'blog_article_categories.category_id')
            ->where('blog_article_categories.article_id', '=', $articleId)
            ->get();
    }

    /**
     * Get tags for an article
     */
    public static function getTags(int $articleId): array {
        return QueryBuilder::table('blog_tags')
            ->select(['blog_tags.*'])
            ->join('blog_article_tags', 'blog_tags.id', '=', 'blog_article_tags.tag_id')
            ->where('blog_article_tags.article_id', '=', $articleId)
            ->get();
    }

    /**
     * Attach categories to article
     */
    public static function attachCategories(int $articleId, array $categoryIds): void {
        // Remove existing
        QueryBuilder::table('blog_article_categories')
            ->where('article_id', '=', $articleId)
            ->delete();

        // Add new
        foreach ($categoryIds as $categoryId) {
            QueryBuilder::table('blog_article_categories')->insert([
                'article_id' => $articleId,
                'category_id' => $categoryId
            ]);
        }
    }

    /**
     * Attach tags to article
     */
    public static function attachTags(int $articleId, array $tagIds): void {
        // Remove existing
        QueryBuilder::table('blog_article_tags')
            ->where('article_id', '=', $articleId)
            ->delete();

        // Add new
        foreach ($tagIds as $tagId) {
            QueryBuilder::table('blog_article_tags')->insert([
                'article_id' => $articleId,
                'tag_id' => $tagId
            ]);
        }
    }
}
?>
