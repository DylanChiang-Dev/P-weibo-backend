<?php
namespace App\Services;

use App\Models\BlogArticle;
use App\Models\BlogCategory;
use App\Models\BlogTag;
use App\Core\Logger;

class BlogService {
    /**
     * Generate unique slug from title
     */
    public function generateSlug(string $title, ?int $excludeId = null): string {
        $slug = strtolower($title);
        // Replace Chinese and special characters with hyphen
        $slug = preg_replace('/[^a-z0-9\x{4e00}-\x{9fa5}]+/u', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $original = $slug;
        $count = 1;
        while (BlogArticle::existsBySlug($slug) && $this->getArticleBySlug($slug, $excludeId)) {
            $slug = $original . '-' . $count++;
        }
        
        return $slug;
    }

    /**
     * Get article by slug (exclude specific ID)
     */
    private function getArticleBySlug(string $slug, ?int $excludeId): ?array {
        $article = BlogArticle::getBySlug($slug);
        if (!$article) return null;
        if ($excludeId && (int)$article['id'] === $excludeId) return null;
        return $article;
    }

    /**
     * Create article with categories and tags
     */
    public function createArticle(array $data): int {
        // Generate slug if not provided
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }

        // Auto-generate excerpt if not provided
        if (empty($data['excerpt']) && !empty($data['content'])) {
            $data['excerpt'] = $this->extractExcerpt($data['content']);
        }

        // Extract categories and tags
        $categoryIds = $data['category_ids'] ?? [];
        $tagIds = $data['tag_ids'] ?? [];
        unset($data['category_ids'], $data['tag_ids']);

        // Create article
        $articleId = BlogArticle::create($data);

        // Attach categories and tags
        if (!empty($categoryIds)) {
            BlogArticle::attachCategories($articleId, $categoryIds);
        }
        if (!empty($tagIds)) {
            BlogArticle::attachTags($articleId, $tagIds);
        }

        Logger::info('blog_article_created', ['id' => $articleId, 'title' => $data['title']]);
        return $articleId;
    }

    /**
     * Extract excerpt from content
     */
    private function extractExcerpt(string $content, int $length = 200): string {
        // Remove Markdown syntax
        $text = $content;
        
        // Remove headings
        $text = preg_replace('/^#+\s+/m', '', $text);
        
        // Remove bold/italic
        $text = preg_replace('/[\*_]{1,2}([^\*_]+)[\*_]{1,2}/', '$1', $text);
        
        // Remove links [text](url)
        $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);
        
        // Remove code blocks
        $text = preg_replace('/```[^`]*```/', '', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        
        // Remove images
        $text = preg_replace('/!\[([^\]]*)\]\([^\)]+\)/', '', $text);
        
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // Truncate
        if (mb_strlen($text) > $length) {
            $text = mb_substr($text, 0, $length) . '...';
        }
        
        return $text;
    }

    /**
     * Update article
     */
    public function updateArticle(int $id, array $data): void {
        // Update slug if title changed
        $article = BlogArticle::getById($id);
        if ($article && isset($data['title']) && $data['title'] !== $article['title']) {
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['title'], $id);
            }
        }

        // Extract categories and tags
        $categoryIds = $data['category_ids'] ?? null;
        $tagIds = $data['tag_ids'] ?? null;
        unset($data['category_ids'], $data['tag_ids']);

        // Update article
        BlogArticle::update($id, $data);

        // Update categories and tags if provided
        if ($categoryIds !== null) {
            BlogArticle::attachCategories($id, $categoryIds);
        }
        if ($tagIds !== null) {
            BlogArticle::attachTags($id, $tagIds);
        }

        Logger::info('blog_article_updated', ['id' => $id]);
    }

    /**
     * Get formatted article with categories and tags
     */
    public function getArticle(string $slug, bool $incrementView = false): ?array {
        $article = BlogArticle::getBySlug($slug);
        if (!$article) return null;

        $articleId = (int)$article['id'];

        // Increment view count
        if ($incrementView) {
            BlogArticle::incrementViewCount($articleId);
            $article['view_count'] = ((int)$article['view_count']) + 1;
        }

        // Get categories and tags
        $article['categories'] = BlogArticle::getCategories($articleId);
        $article['tags'] = BlogArticle::getTags($articleId);

        return $this->formatArticle($article);
    }

    /**
     * Get article list with pagination
     */
    public function getArticles(int $limit = 20, ?string $cursor = null, string $status = 'published'): array {
        $articles = BlogArticle::list($limit, $cursor, $status);
        
        $formatted = [];
        foreach ($articles as $article) {
            $articleId = (int)$article['id'];
            $article['categories'] = BlogArticle::getCategories($articleId);
            $article['tags'] = BlogArticle::getTags($articleId);
            $formatted[] = $this->formatArticle($article, true);
        }

        return $formatted;
    }

    /**
     * Format article for response
     */
    private function formatArticle(array $article, bool $listMode = false): array {
        $formatted = [
            'id' => (int)$article['id'],
            'title' => $article['title'],
            'slug' => $article['slug'],
            'excerpt' => $article['excerpt'],
            'cover_image' => $article['cover_image'],
            'status' => $article['status'],
            'visibility' => $article['visibility'],
            'view_count' => (int)($article['view_count'] ?? 0),
            'published_at' => $article['published_at'],
            'created_at' => $article['created_at'],
            'updated_at' => $article['updated_at'],
            'author' => [
                'id' => (int)$article['user_id'],
                'email' => $article['email'] ?? null,
                'display_name' => $article['display_name'] ?? null,
                'avatar_path' => $article['avatar_path'] ?? null,
            ],
            'categories' => $article['categories'] ?? [],
            'tags' => $article['tags'] ?? [],
        ];

        // Include content only in detail mode
        if (!$listMode) {
            $formatted['content'] = $article['content'];
        }

        return $formatted;
    }

    /**
     * Process tag names to IDs
     */
    public function processTagNames(array $tagNames): array {
        $tagIds = [];
        foreach ($tagNames as $name) {
            $tagIds[] = BlogTag::findOrCreate(trim($name));
        }
        return $tagIds;
    }
}
?>
