<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Core\Logger;
use App\Core\Validator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use App\Services\PostService;
use App\Models\Comment;

class PostController {
    private PostService $postService;

    public function __construct() {
        $config = \config();
        $this->postService = new PostService($config['upload']);
    }

    public function create(Request $req): void {
        $userId = (int)$req->user['id'];
        $content = $_POST['content'] ?? '';
        $visibility = $_POST['visibility'] ?? 'public';
        
        if (empty(trim($content))) {
            throw new ValidationException('Content is required');
        }
        
        $images = $req->files['images'] ?? [];
        $videos = $req->files['videos'] ?? [];
        
        $postId = $this->postService->createPost($userId, $content, $visibility, $images, $videos);
        ApiResponse::success(['id' => $postId], 201);
    }

    public function list(Request $req): void {
        $limit = isset($req->query['limit']) ? max(1, min(100, (int)$req->query['limit'])) : 20;
        $cursor = $req->query['cursor'] ?? null;
        $currentUserId = $req->user['id'] ?? null;
        
        $data = $this->postService->getPosts($limit, $cursor, $currentUserId);
        ApiResponse::success($data);
    }

    public function get(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $currentUserId = isset($req->user['id']) ? (int)$req->user['id'] : null;
        
        Logger::info('get_post_request', [
            'post_id' => $id,
            'current_user_id' => $currentUserId,
            'has_user' => isset($req->user),
            'user_data' => $req->user ?? null
        ]);
        
        $data = $this->postService->getPost($id, $currentUserId);
        
        if (!$data) throw new NotFoundException('Post not found');
        ApiResponse::success($data);
    }

    public function like(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $cnt = $this->postService->likePost((int)$req->user['id'], $id);
        ApiResponse::success(['like_count' => $cnt]);
    }

    public function createComment(Request $req, array $params): void {
        $data = is_array($req->body) ? $req->body : [];
        $errs = Validator::required($data, ['content']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);

        $userId = $req->user ? (int)$req->user['id'] : null;
        $authorName = $data['authorName'] ?? null;

        if (!$userId && empty($authorName)) {
             throw new ValidationException('Author name is required for guest comments');
        }

        $this->postService->commentPost($userId, (int)$params['id'], $data['content'], $authorName);
        ApiResponse::success();
    }

    public function delete(Request $req, array $params): void {
        $user = $req->user;
        $id = (int)($params['id'] ?? 0);

        $this->postService->deletePost($user['id'], $id);
        ApiResponse::success();
    }

    public function getComments(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        
        $comments = $this->postService->getComments($id);
        ApiResponse::success($comments);
    }

    public function pin(Request $req, array $params): void {
        $userId = (int)$req->user['id'];
        $postId = (int)($params['id'] ?? 0);

        $this->postService->pinPost($userId, $postId);
        ApiResponse::success();
    }

    public function unpin(Request $req, array $params): void {
        $userId = (int)$req->user['id'];
        $postId = (int)($params['id'] ?? 0);

        $this->postService->unpinPost($userId, $postId);
        ApiResponse::success();
    }

    public function update(Request $req, array $params): void {
        $userId = (int)$req->user['id'];
        $postId = (int)($params['id'] ?? 0);
        
        // Support both JSON and FormData
        $content = $_POST['content'] ?? $req->body['content'] ?? null;
        $createdAt = $_POST['created_at'] ?? $req->body['created_at'] ?? null;
        $visibility = $_POST['visibility'] ?? $req->body['visibility'] ?? null;
        
        // Parse delete_images[] and delete_videos[] arrays
        $deleteImageIds = [];
        $deleteVideoIds = [];
        
        if (isset($_POST['delete_images'])) {
            $deleteImageIds = is_array($_POST['delete_images']) 
                ? array_map('intval', $_POST['delete_images']) 
                : [intval($_POST['delete_images'])];
        }
        
        if (isset($_POST['delete_videos'])) {
            $deleteVideoIds = is_array($_POST['delete_videos']) 
                ? array_map('intval', $_POST['delete_videos']) 
                : [intval($_POST['delete_videos'])];
        }
        
        // Get uploaded files
        $imageFiles = $req->files['images'] ?? [];
        $videoFiles = $req->files['videos'] ?? [];

        $result = $this->postService->updatePost(
            $userId, 
            $postId, 
            $content, 
            $createdAt,
            $visibility,
            $deleteImageIds,
            $deleteVideoIds,
            $imageFiles,
            $videoFiles
        );
        ApiResponse::success($result);
    }

    /**
     * Update post with media files (POST endpoint to support file uploads)
     * 
     * This is a workaround for PHP's limitation: PATCH requests don't populate $_FILES.
     * Frontend should use this endpoint when updating posts with new images/videos.
     */
    public function updateWithMedia(Request $req, array $params): void {
        // Reuse the exact same logic as update()
        $userId = (int)$req->user['id'];
        $postId = (int)($params['id'] ?? 0);
        
        // Support both JSON and FormData
        $content = $_POST['content'] ?? $req->body['content'] ?? null;
        $createdAt = $_POST['created_at'] ?? $req->body['created_at'] ?? null;
        $visibility = $_POST['visibility'] ?? $req->body['visibility'] ?? null;
        
        // Parse delete_images[] and delete_videos[] arrays
        $deleteImageIds = [];
        $deleteVideoIds = [];
        
        if (isset($_POST['delete_images'])) {
            $deleteImageIds = is_array($_POST['delete_images']) 
                ? array_map('intval', $_POST['delete_images']) 
                : [intval($_POST['delete_images'])];
        }
        
        if (isset($_POST['delete_videos'])) {
            $deleteVideoIds = is_array($_POST['delete_videos']) 
                ? array_map('intval', $_POST['delete_videos']) 
                : [intval($_POST['delete_videos'])];
        }
        
        // Get uploaded files (works with POST!)
        $imageFiles = $req->files['images'] ?? [];
        $videoFiles = $req->files['videos'] ?? [];

        $result = $this->postService->updatePost(
            $userId, 
            $postId, 
            $content, 
            $createdAt,
            $visibility,
            $deleteImageIds,
            $deleteVideoIds,
            $imageFiles,
            $videoFiles
        );
        ApiResponse::success($result);
    }

    public function deleteComment(Request $req, array $params): void {
        $commentId = (int)($params['id'] ?? 0);
        $comment = Comment::getById($commentId);
        
        if (!$comment) {
            throw new NotFoundException('Comment not found');
        }
        
        // Permission check: comment author OR admin
        $currentUserId = $req->user['id'] ?? null;
        $isAuthor = $currentUserId && (int)$comment['user_id'] === (int)$currentUserId;
        $isAdmin = isset($req->user['role']) && $req->user['role'] === 'admin';
        
        if (!$isAuthor && !$isAdmin) {
            throw new ForbiddenException('You can only delete your own comments');
        }
        
        Comment::delete($commentId);
        ApiResponse::success();
    }
}
?>