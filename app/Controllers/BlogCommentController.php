<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Core\Validator;
use App\Core\Logger;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use App\Models\BlogComment;
use App\Models\BlogArticle;

class BlogCommentController {
    /**
     * Get comments for an article
     */
    public function list(Request $req, array $params): void {
        $articleId = (int)($params['id'] ?? 0);
        
        // Admin can see all, others only approved
        $isAdmin = isset($req->user) && isset($req->user['role']) && $req->user['role'] === 'admin';
        $status = $isAdmin && isset($req->query['status']) ? $req->query['status'] : 'approved';

        $comments = BlogComment::getByArticle($articleId, $status);

        // Format comments
        $formatted = array_map(function($comment) {
            return [
                'id' => (int)$comment['id'],
                'content' => $comment['content'],
                'status' => $comment['status'],
                'created_at' => $comment['created_at'],
                'author' => $comment['user_id'] ? [
                    'type' => 'user',
                    'display_name' => $comment['display_name'] ?? $comment['email'],
                    'avatar_path' => $comment['avatar_path'] ?? null,
                ] : [
                    'type' => 'guest',
                    'display_name' => $comment['author_name'],
                ]
            ];
        }, $comments);

        ApiResponse::success($formatted);
    }

    /**
     * Create a comment (guest or user)
     */
    public function create(Request $req, array $params): void {
        $articleId = (int)($params['id'] ?? 0);
        
        // Check if article exists
        $article = BlogArticle::getById($articleId);
        if (!$article) {
            throw new NotFoundException('Article not found');
        }

        $data = is_array($req->body) ? $req->body : [];
        
        // Validation
        $errs = Validator::required($data, ['content']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);

        $userId = $req->user['id'] ?? null;
        
        $commentData = [
            'article_id' => $articleId,
            'content' => $data['content'],
            'ip_address' => $req->ip(),
            'user_agent' => $req->userAgent(),
        ];

        if ($userId) {
            // Logged in user - auto approve
            $commentData['user_id'] = $userId;
            $commentData['status'] = 'approved';
        } else {
            // Guest comment - require moderation
            $errs = Validator::required($data, ['author_name', 'author_email']);
            if (!empty($errs)) throw new ValidationException('Guest comments require name and email', $errs);
            
            if (!Validator::email($data['author_email'])) {
                throw new ValidationException('Invalid email format');
            }

            $commentData['author_name'] = $data['author_name'];
            $commentData['author_email'] = $data['author_email'];
            $commentData['status'] = 'pending';
        }

        $commentId = BlogComment::create($commentData);

        Logger::info('blog_comment_created', [
            'id' => $commentId,
            'article_id' => $articleId,
            'user_id' => $userId,
            'status' => $commentData['status']
        ]);

        ApiResponse::success([
            'id' => $commentId,
            'status' => $commentData['status'],
            'message' => $commentData['status'] === 'pending' 
                ? 'Comment submitted for moderation' 
                : 'Comment posted'
        ], 201);
    }

    /**
     * Get pending comments for moderation (Admin only)
     */
    public function getPending(Request $req): void {
        $comments = BlogComment::getPending();
        
        $formatted =array_map(function($comment) {
            return [
                'id' => (int)$comment['id'],
                'article_id' => (int)$comment['article_id'],
                'article_title' => $comment['article_title'],
                'content' => $comment['content'],
                'author_name' => $comment['author_name'],
                'author_email' => $comment['author_email'],
                'ip_address' => $comment['ip_address'],
                'created_at' => $comment['created_at'],
            ];
        }, $comments);

        ApiResponse::success($formatted);
    }

    /**
     * Approve comment (Admin only)
     */
    public function approve(Request $req, array $params): void {
        $commentId = (int)($params['id'] ?? 0);
        $comment = BlogComment::getById($commentId);
        
        if (!$comment) {
            throw new NotFoundException('Comment not found');
        }

        BlogComment::updateStatus($commentId, 'approved');
        Logger::info('blog_comment_approved', ['id' => $commentId]);

        ApiResponse::success();
    }

    /**
     * Reject comment (Admin only)
     */
    public function reject(Request $req, array $params): void {
        $commentId = (int)($params['id'] ?? 0);
        $comment = BlogComment::getById($commentId);
        
        if (!$comment) {
            throw new NotFoundException('Comment not found');
        }

        BlogComment::updateStatus($commentId, 'rejected');
        Logger::info('blog_comment_rejected', ['id' => $commentId]);

        ApiResponse::success();
    }

    /**
     * Delete comment (Admin only)
     */
    public function delete(Request $req, array $params): void {
        $commentId = (int)($params['id'] ?? 0);
        $comment = BlogComment::getById($commentId);
        
        if (!$comment) {
            throw new NotFoundException('Comment not found');
        }

        BlogComment::delete($commentId);
        Logger::info('blog_comment_deleted', ['id' => $commentId]);

        ApiResponse::success();
    }
}
?>
