<?php
namespace App\Services;

use App\Models\Post;
use App\Models\PostImage;
use App\Models\PostVideo;
use App\Models\Like;
use App\Models\Comment;
use App\Core\Database;
use App\Core\Logger;
use App\Services\ImageService;
use App\Services\MediaService;

class PostService {
    private ImageService $imageService;
    private MediaService $mediaService;

    public function __construct(array $uploadConfig) {
        $this->imageService = new ImageService($uploadConfig['path'], $uploadConfig['max_image_mb'] ?? $uploadConfig['max_mb']);
        $this->mediaService = new MediaService($uploadConfig['path'], $uploadConfig['max_video_mb'] ?? 100);
    }

    public function createPost(int $userId, string $content, array $imageFiles = [], array $videoFiles = []): int {
        try {
            Database::begin();
            $postId = Post::create($userId, $content);

            // Process images
            if (!empty($imageFiles)) {
                $count = is_array($imageFiles['name']) ? count($imageFiles['name']) : 1;
                for ($i = 0; $i < $count; $i++) {
                    $file = [
                        'name' => is_array($imageFiles['name']) ? $imageFiles['name'][$i] : $imageFiles['name'],
                        'type' => is_array($imageFiles['type']) ? $imageFiles['type'][$i] : $imageFiles['type'],
                        'tmp_name' => is_array($imageFiles['tmp_name']) ? $imageFiles['tmp_name'][$i] : $imageFiles['tmp_name'],
                        'error' => is_array($imageFiles['error']) ? $imageFiles['error'][$i] : $imageFiles['error'],
                        'size' => is_array($imageFiles['size']) ? $imageFiles['size'][$i] : $imageFiles['size'],
                    ];
                    
                    try {
                        $res = $this->imageService->process($file);
                        PostImage::add($postId, $res['original'], $res['width'], $res['height']);
                    } catch (\Throwable $e) {
                        Logger::warn('image_process_failed', ['error' => $e->getMessage()]);
                    }
                }
            }

            // Process videos
            if (!empty($videoFiles)) {
                $count = is_array($videoFiles['name']) ? count($videoFiles['name']) : 1;
                for ($i = 0; $i < $count; $i++) {
                    $file = [
                        'name' => is_array($videoFiles['name']) ? $videoFiles['name'][$i] : $videoFiles['name'],
                        'type' => is_array($videoFiles['type']) ? $videoFiles['type'][$i] : $videoFiles['type'],
                        'tmp_name' => is_array($videoFiles['tmp_name']) ? $videoFiles['tmp_name'][$i] : $videoFiles['tmp_name'],
                        'error' => is_array($videoFiles['error']) ? $videoFiles['error'][$i] : $videoFiles['error'],
                        'size' => is_array($videoFiles['size']) ? $videoFiles['size'][$i] : $videoFiles['size'],
                    ];
                    
                    try {
                        $res = $this->mediaService->process($file);
                        PostVideo::add(
                            $postId,
                            $res['file_path'],
                            $res['thumbnail_path'],
                            $res['duration'],
                            $res['file_size'],
                            $res['mime_type']
                        );
                    } catch (\Throwable $e) {
                        Logger::warn('video_process_failed', ['error' => $e->getMessage()]);
                    }
                }
            }
            
            Database::commit();
            return $postId;
        } catch (\Throwable $e) {
            Database::rollback();
            Logger::error('post_create_failed', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Internal Server Error', 500);
        }
    }

    public function getPosts(int $limit, ?string $cursor): array {
        $rows = Post::list($limit, $cursor);
        $result = [];
        foreach ($rows as $row) {
            $result[] = $this->formatPost($row);
        }
        
        $nextCursor = null;
        if (!empty($rows)) {
            $last = end($rows);
            $nextCursor = base64_encode($last['created_at'] . '|' . $last['id']);
        }
        
        return ['items' => $result, 'next_cursor' => $nextCursor];
    }

    public function getPost(int $id): ?array {
        $row = Post::getById($id);
        if (!$row) return null;
        return $this->formatPost($row);
    }

    public function likePost(int $userId, int $postId): int {
        Like::add($userId, $postId);
        return Like::countByPost($postId);
    }

    public function commentPost(?int $userId, int $postId, string $content, ?string $authorName = null): int {
        if (empty(trim($content))) throw new \InvalidArgumentException('Empty comment');
        if (!$userId && empty($authorName)) throw new \InvalidArgumentException('Author name required for guests');
        
        return Comment::add($userId, $postId, $content, $authorName);
    }

    public function deletePost(int $userId, int $postId): void {
        $post = Post::getById($postId);
        if (!$post) {
            throw new \RuntimeException('Post not found');
        }
        if ((int)$post['user_id'] !== $userId) {
            throw new \RuntimeException('Forbidden');
        }
        Post::softDelete($postId);
    }

    public function pinPost(int $userId, int $postId): void {
        $post = Post::getById($postId);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }
        // Only the post owner can pin (in single-user system, this is the admin)
        if ((int)$post['user_id'] !== $userId) {
            throw new \RuntimeException('Forbidden', 403);
        }
        Post::pin($postId);
    }

    public function unpinPost(int $userId, int $postId): void {
        $post = Post::getById($postId);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }
        // Only the post owner can unpin (in single-user system, this is the admin)
        if ((int)$post['user_id'] !== $userId) {
            throw new \RuntimeException('Forbidden', 403);
        }
        Post::unpin($postId);
    }

    public function getComments(int $postId): array {
        $rows = Comment::listByPost($postId);
        return array_map(fn($row) => [
            'id' => (int)$row['id'],
            'content' => $row['content'],
            'created_at' => $row['created_at'],
            'author' => [
                'id' => $row['user_id'] ? (int)$row['user_id'] : null,
                'email' => $row['email'],
                'display_name' => $row['user_id'] ? $row['display_name'] : ($row['author_name'] ?? 'Guest'),
                'avatar_path' => $row['avatar_path']
            ]
        ], $rows);
    }

    private function formatPost(array $row): array {
        $images = PostImage::listByPost((int)$row['id']);
        $videos = PostVideo::listByPost((int)$row['id']);
        
        return [
            'id' => (int)$row['id'],
            'content' => $row['content'],
            'is_pinned' => (bool)$row['is_pinned'],
            'created_at' => $row['created_at'],
            'author' => [
                'id' => (int)$row['user_id'], 
                'email' => $row['email'],
                'display_name' => $row['display_name'],
                'avatar_path' => $row['avatar_path']
            ],
            'images' => array_map(fn($i) => [
                'file_path' => $i['file_path'], 
                'width' => (int)$i['width'], 
                'height' => (int)$i['height']
            ], $images),
            'videos' => array_map(fn($v) => [
                'file_path' => $v['file_path'],
                'thumbnail_path' => $v['thumbnail_path'],
                'duration' => $v['duration'] ? (int)$v['duration'] : null,
                'file_size' => $v['file_size'] ? (int)$v['file_size'] : null,
                'mime_type' => $v['mime_type']
            ], $videos),
            'like_count' => Like::countByPost((int)$row['id']),
            'comment_count' => Comment::countByPost((int)$row['id']),
        ];
    }
}
