<?php
namespace App\Models;

use App\Core\QueryBuilder;

class BlogArticleRevision {
    /**
     * Create a revision
     */
    public static function create(int $articleId, array $data, int $createdBy): int {
        // Get current revision number
        $currentRev = self::getLatestRevisionNumber($articleId);
        
        return QueryBuilder::table('blog_article_revisions')->insert([
            'article_id' => $articleId,
            'title' => $data['title'],
            'content' => $data['content'],
            'excerpt' => $data['excerpt'] ?? null,
            'revision_number' => $currentRev + 1,
            'created_by' => $createdBy
        ]);
    }

    /**
     * Get latest revision number
     */
    private static function getLatestRevisionNumber(int $articleId): int {
        $result = QueryBuilder::table('blog_article_revisions')
            ->select(['MAX(revision_number) as max_rev'])
            ->where('article_id', '=', $articleId)
            ->first();
        
        return (int)($result['max_rev'] ?? 0);
    }

    /**
     * Get revisions for an article
     */
    public static function getByArticle(int $articleId, int $limit = 20): array {
        return QueryBuilder::table('blog_article_revisions')
            ->select(['blog_article_revisions.*', 'users.email', 'users.display_name'])
            ->join('users', 'blog_article_revisions.created_by', '=', 'users.id')
            ->where('blog_article_revisions.article_id', '=', $articleId)
            ->orderBy('blog_article_revisions.revision_number', 'DESC')
            ->limit($limit)
            ->get();
    }

    /**
     * Get specific revision
     */
    public static function getByRevisionNumber(int $articleId, int $revNumber): ?array {
        return QueryBuilder::table('blog_article_revisions')
            ->where('article_id', '=', $articleId)
            ->where('revision_number', '=', $revNumber)
            ->first();
    }

    /**
     * Delete old revisions (keep only latest N)
     */
    public static function pruneOldRevisions(int $articleId, int $keepCount = 20): void {
        $latestRev = self::getLatestRevisionNumber($articleId);
        $cutoffRev = $latestRev - $keepCount;
        
        if ($cutoffRev > 0) {
            QueryBuilder::table('blog_article_revisions')
                ->where('article_id', '=', $articleId)
                ->where('revision_number', '<=', $cutoffRev)
                ->delete();
        }
    }
}
?>
