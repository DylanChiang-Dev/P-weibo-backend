<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Core\Validator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use App\Services\BlogService;
use App\Models\BlogArticle;

class BlogController {
    private BlogService $blogService;

    public function __construct() {
        $this->blogService = new BlogService();
    }

    /**
     * Create a new blog article (Admin only)
     */
    public function create(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        
        // Validation
        $errs = Validator::required($data, ['title', 'content']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);

        $userId = (int)$req->user['id'];
        
        // Prepare article data
        $articleData = [
            'user_id' => $userId,
            'title' => $data['title'],
            'content' => $data['content'],
            'excerpt' => $data['excerpt'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'visibility' => $data['visibility'] ?? 'public',
            'slug' => $data['slug'] ?? null,
        ];

        // Set published_at if publishing
        if ($articleData['status'] === 'published' && empty($data['published_at'])) {
            $articleData['published_at'] = date('Y-m-d H:i:s');
        } elseif (isset($data['published_at'])) {
            $articleData['published_at'] = $data['published_at'];
        }

        // Process categories and tags
        $articleData['category_ids'] = $data['category_ids'] ?? [];
        
        if (isset($data['tag_names']) && is_array($data['tag_names'])) {
            $articleData['tag_ids'] = $this->blogService->processTagNames($data['tag_names']);
        } else {
            $articleData['tag_ids'] = $data['tag_ids'] ?? [];
        }

        $articleId = $this->blogService->createArticle($articleData);
        
        ApiResponse::success(['id' => $articleId], 201);
    }

    /**
     * Get article list
     */
    public function list(Request $req): void {
        $limit = min((int)($req->query['limit'] ?? 20), 50);
        $cursor = $req->query['cursor'] ?? null;
        
        // Only show published articles to non-admin
        $isAdmin = isset($req->user['role']) && $req->user['role'] === 'admin';
        $status = $isAdmin && isset($req->query['status']) ? $req->query['status'] : 'published';

        $articles = $this->blogService->getArticles($limit, $cursor, $status);

        // Generate next cursor
        $nextCursor = null;
        if (count($articles) === $limit) {
            $last = end($articles);
            $nextCursor = base64_encode($last['created_at'] . '|' . $last['id']);
        }

        ApiResponse::success([
            'items' => $articles,
            'meta' => [
                'has_more' => $nextCursor !== null,
                'cursor' => $nextCursor
            ]
        ]);
    }

    /**
     * Get article by slug
     */
    public function get(Request $req, array $params): void {
        $slug = $params['slug'] ?? '';
        
        // Increment view count for non-admin
        $isAdmin = isset($req->user['role']) && $req->user['role'] === 'admin';
        $incrementView = !$isAdmin;

        $article = $this->blogService->getArticle($slug, $incrementView);
        
        if (!$article) {
            throw new NotFoundException('Article not found');
        }

        // Check visibility
        if ($article['status'] !== 'published' && !$isAdmin) {
            throw new NotFoundException('Article not found');
        }

        if ($article['visibility'] === 'private' && !$isAdmin) {
            throw new ForbiddenException('This article is private');
        }

        ApiResponse::success($article);
    }

    /**
     * Update article (Admin only)
     */
    public function update(Request $req, array $params): void {
        $articleId = (int)($params['id'] ?? 0);
        $article = BlogArticle::getById($articleId);
        
        if (!$article) {
            throw new NotFoundException('Article not found');
        }

        $data = is_array($req->body) ? $req->body : [];

        // Prepare update data
        $updateData = [];
        $allowedFields = ['title', 'content', 'excerpt', 'cover_image', 'status', 'visibility', 'slug'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        // Update published_at if status changed to published
        if (isset($updateData['status']) && $updateData['status'] === 'published' && $article['status'] !== 'published') {
            $updateData['published_at'] = date('Y-m-d H:i:s');
        }

        // Process categories and tags
        if (isset($data['category_ids'])) {
            $updateData['category_ids'] = $data['category_ids'];
        }
        
        if (isset($data['tag_names']) && is_array($data['tag_names'])) {
            $updateData['tag_ids'] = $this->blogService->processTagNames($data['tag_names']);
        } elseif (isset($data['tag_ids'])) {
            $updateData['tag_ids'] = $data['tag_ids'];
        }

        $this->blogService->updateArticle($articleId, $updateData);

        // Return updated article
        $updated = $this->blogService->getArticle($article['slug']);
        ApiResponse::success($updated);
    }

    /**
     * Delete article (Admin only)
     */
    public function delete(Request $req, array $params): void {
        $articleId = (int)($params['id'] ?? 0);
        $article = BlogArticle::getById($articleId);
        
        if (!$article) {
            throw new NotFoundException('Article not found');
        }

        BlogArticle::delete($articleId);
        ApiResponse::success();
    }

    /**
     * Publish article (Admin only)
     */
    public function publish(Request $req, array $params): void {
        $articleId = (int)($params['id'] ?? 0);
        $article = BlogArticle::getById($articleId);
        
        if (!$article) {
            throw new NotFoundException('Article not found');
        }

        BlogArticle::update($articleId, [
            'status' => 'published',
            'published_at' => date('Y-m-d H:i:s')
        ]);

        ApiResponse::success();
    }

    /**
     * Toggle like for an article
     */
    public function like(Request $req, array $params): void {
        $articleId = (int)($params['id'] ?? 0);
        $article = BlogArticle::getById($articleId);
        
        if (!$article) {
            throw new NotFoundException('Article not found');
        }

        $userId = $req->user['id'] ?? null;
        $ipAddress = $req->ip();

        $isLiked = \App\Models\BlogArticleLike::toggle($articleId, $userId, $ipAddress);

        // Update like count
        $likeCount = \App\Models\BlogArticleLike::count($articleId);
        BlogArticle::update($articleId, ['like_count' => $likeCount]);

        ApiResponse::success([
            'liked' => $isLiked,
            'like_count' => $likeCount
        ]);
    }

    /**
     * Get like status
     */
    public function getLikeStatus(Request $req, array $params): void {
        $articleId = (int)($params['id'] ?? 0);
        $userId = $req->user['id'] ?? null;
        $ipAddress = $req->ip();

        $isLiked = \App\Models\BlogArticleLike::isLiked($articleId, $userId, $ipAddress);
        $likeCount = \App\Models\BlogArticleLike::count($articleId);

        ApiResponse::success([
            'liked' => $isLiked,
            'like_count' => $likeCount  
        ]);
    }

    /**
     * Search articles (full-text search)
     */
    public function search(Request $req): void {
        $keyword = $req->query['q'] ?? '';
        
        if (empty($keyword)) {
            throw new ValidationException('Search keyword is required');
        }

        $limit = min((int)($req->query['limit'] ?? 20), 50);
        
        $db = \App\Core\Database::getInstance();
        $pdo = $db->getPdo();
        
        // Use FULLTEXT search
        $sql = "
            SELECT 
                blog_articles.*,
                users.email,
                users.display_name,
                users.avatar_path,
                MATCH(blog_articles.title, blog_articles.content) AGAINST(:keyword IN NATURAL LANGUAGE MODE) as relevance
            FROM blog_articles
            LEFT JOIN users ON blog_articles.user_id = users.id
            WHERE blog_articles.status = 'published'
            AND MATCH(blog_articles.title, blog_articles.content) AGAINST(:keyword IN NATURAL LANGUAGE MODE)
            ORDER BY relevance DESC, blog_articles.published_at DESC
            LIMIT :limit
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':keyword', $keyword, \PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        
        $articles = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Format results
        $formatted = [];
        foreach ($articles as $article) {
            $articleId = (int)$article['id'];
            $article['categories'] = BlogArticle::getCategories($articleId);
            $article['tags'] = BlogArticle::getTags($articleId);
            $formatted[] = [
                'id' => (int)$article['id'],
                'title' => $article['title'],
                'slug' => $article['slug'],
                'excerpt' => $article['excerpt'],
                'cover_image' => $article['cover_image'],
                'published_at' => $article['published_at'],
                'relevance' => (float)$article['relevance'],
                'author' => [
                    'display_name' => $article['display_name'] ?? $article['email'],
                ],
                'categories' => $article['categories'],
                'tags' => $article['tags'],
            ];
        }
        
        ApiResponse::success([
            'items' => $formatted,
            'keyword' => $keyword,
            'count' => count($formatted)
        ]);
    }
}
?>
