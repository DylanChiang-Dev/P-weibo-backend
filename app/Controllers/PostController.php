<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Logger;
use App\Core\Validator;
use App\Services\PostService;

class PostController {
    private PostService $postService;

    public function __construct() {
        $config = \config();
        $this->postService = new PostService($config['upload']);
    }

    public function create(Request $req): void {
        $userId = (int)$req->user['id'];
        $content = is_array($req->body) ? trim((string)($req->body['content'] ?? '')) : '';
        if ($content === '') Response::json(['success' => false, 'error' => 'Content required'], 400);

        $imageFiles = $req->files['images'] ?? [];
        $videoFiles = $req->files['videos'] ?? [];

        try {
            $postId = $this->postService->createPost($userId, $content, $imageFiles, $videoFiles);
            Response::json(['success' => true, 'data' => ['id' => $postId]], 201);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function list(Request $req): void {
        $limit = isset($req->query['limit']) ? max(1, min(100, (int)$req->query['limit'])) : 20;
        $cursor = $req->query['cursor'] ?? null;
        
        $data = $this->postService->getPosts($limit, $cursor);
        Response::json(['success' => true, 'data' => $data]);
    }

    public function get(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $data = $this->postService->getPost($id);
        
        if (!$data) Response::json(['success' => false, 'error' => 'Not Found'], 404);
        Response::json(['success' => true, 'data' => $data]);
    }

    public function like(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $cnt = $this->postService->likePost((int)$req->user['id'], $id);
        Response::json(['success' => true, 'data' => ['like_count' => $cnt]]);
    }

    public function createComment(Request $req, array $params): void {
        $data = is_array($req->body) ? $req->body : [];
        $errs = Validator::required($data, ['content']);
        if (!empty($errs)) Response::json(['success' => false, 'error' => 'Bad Request', 'details' => $errs], 400);

        $userId = $req->user ? (int)$req->user['id'] : null;
        $authorName = $data['authorName'] ?? null;

        if (!$userId && empty($authorName)) {
             Response::json(['success' => false, 'error' => 'Author name is required for guests'], 400);
        }

        try {
            $this->postService->commentPost($userId, (int)$params['id'], $data['content'], $authorName);
            Response::json(['success' => true]);
        } catch (\InvalidArgumentException $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 400);
        } catch (\RuntimeException $e) {
            Response::json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function delete(Request $req, array $params): void {
        $user = $req->user;
        $id = (int)($params['id'] ?? 0);

        try {
            $this->postService->deletePost($user['id'], $id);
            Response::json(['success' => true]);
        } catch (\RuntimeException $e) {
            $code = $e->getMessage() === 'Forbidden' ? 403 : 404;
            Response::json(['success' => false, 'error' => $e->getMessage()], $code);
        } catch (\Throwable $e) {
            Logger::error('delete_post_failed', ['error' => $e->getMessage()]);
            Response::json(['success' => false, 'error' => 'Internal Server Error'], 500);
        }
    }

    public function getComments(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        
        try {
            $comments = $this->postService->getComments($id);
            Response::json(['success' => true, 'data' => $comments]);
        } catch (\Throwable $e) {
            Logger::error('get_comments_failed', ['error' => $e->getMessage()]);
            Response::json(['success' => false, 'error' => 'Internal Server Error'], 500);
        }
    }

    public function pin(Request $req, array $params): void {
        $userId = (int)$req->user['id'];
        $postId = (int)($params['id'] ?? 0);

        try {
            $this->postService->pinPost($userId, $postId);
            Response::json(['success' => true]);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() ?: 500;
            Response::json(['success' => false, 'error' => $e->getMessage()], $code);
        }
    }

    public function unpin(Request $req, array $params): void {
        $userId = (int)$req->user['id'];
        $postId = (int)($params['id'] ?? 0);

        try {
            $this->postService->unpinPost($userId, $postId);
            Response::json(['success' => true]);
        } catch (\RuntimeException $e) {
            $code = $e->getCode() ?: 500;
            Response::json(['success' => false, 'error' => $e->getMessage()], $code);
        }
    }
}
?>