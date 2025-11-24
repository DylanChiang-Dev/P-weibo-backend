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

    public function createPost(int $userId, string $content, string $visibility = 'public', array $imageFiles = [], array $videoFiles = []): int {
        try {
            Database::begin();
            $postId = Post::create($userId, $content, $visibility);

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
            Logger::info('video_upload_start', [
                'count' => $count,
                'files' => $videoFiles['name'] ?? 'unknown'
            ]);
            
            for ($i = 0; $i < $count; $i++) {
                $file = [
                    'name' => is_array($videoFiles['name']) ? $videoFiles['name'][$i] : $videoFiles['name'],
                    'type' => is_array($videoFiles['type']) ? $videoFiles['type'][$i] : $videoFiles['type'],
                    'tmp_name' => is_array($videoFiles['tmp_name']) ? $videoFiles['tmp_name'][$i] : $videoFiles['tmp_name'],
                    'error' => is_array($videoFiles['error']) ? $videoFiles['error'][$i] : $videoFiles['error'],
                    'size' => is_array($videoFiles['size']) ? $videoFiles['size'][$i] : $videoFiles['size'],
                ];
                
                try {
                    Logger::info('video_processing', [
                        'file_name' => $file['name'],
                        'mime_type' => $file['type'],
                        'size' => $file['size'],
                        'tmp_name' => $file['tmp_name'],
                        'post_id' => $postId
                    ]);
                    
                    $res = $this->mediaService->process($file);
                    
                    Logger::info('video_processed', [
                        'file_path' => $res['file_path'],
                        'mime_type' => $res['mime_type'],
                        'file_size' => $res['file_size']
                    ]);
                    
                    PostVideo::add(
                        $postId,
                        $res['file_path'],
                        $res['thumbnail_path'],
                        $res['duration'],
                        $res['file_size'],
                        $res['mime_type']
                    );
                    
                    Logger::info('video_saved_to_db', [
                        'post_id' => $postId,
                        'file_path' => $res['file_path']
                    ]);
                } catch (\Throwable $e) {
                    Logger::error('video_process_failed', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'file' => $file['name'] ?? 'unknown',
                        'post_id' => $postId,
                        'line' => $e->getLine(),
                        'file_error_code' => $file['error']
                    ]);
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

    public function getPosts(int $limit, ?string $cursor, ?int $currentUserId = null): array {
        $rows = Post::list($limit, $cursor, $currentUserId);
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

    public function getPost(int $id, ?int $currentUserId = null): ?array {
        $row = Post::getById($id);
        if (!$row) return null;
        
        // Privacy check: if private, only author can view
        if ($row['visibility'] === 'private') {
            if (!$currentUserId || (int)$row['user_id'] !== (int)$currentUserId) {
                Logger::info('private_post_access_denied', [
                    'post_id' => $id,
                    'post_user_id' => $row['user_id'],
                    'current_user_id' => $currentUserId
                ]);
                return null; // Return null to trigger 404
            }
        }
        
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

    public function updatePost(
        int $userId, 
        int $postId, 
        ?string $content, 
        ?string $createdAt,
        ?string $visibility = null,
        array $deleteImageIds = [],
        array $deleteVideoIds = [],
        array $imageFiles = [],
        array $videoFiles = []
    ): array {
        $post = Post::getById($postId);
        if (!$post) {
            throw new \RuntimeException('Post not found', 404);
        }
        // Only the post owner can update
        if ((int)$post['user_id'] !== $userId) {
            throw new \RuntimeException('Forbidden', 403);
        }

        try {
            Database::begin();

            // Update text content and time
            $updateData = [];
            
            if ($content !== null) {
                $updateData['content'] = $content;
            }
            
            if ($createdAt !== null) {
                // Validate ISO 8601 date format
                $timestamp = strtotime($createdAt);
                if ($timestamp === false) {
                    throw new \InvalidArgumentException('Invalid date format. Use ISO 8601 format (e.g., 2023-12-25T10:00:00Z)');
                }
                // Convert to MySQL datetime format
                $updateData['created_at'] = date('Y-m-d H:i:s', $timestamp);
            }
            
            if ($visibility !== null && in_array($visibility, ['public', 'private'], true)) {
                $updateData['visibility'] = $visibility;
            }

            if (!empty($updateData)) {
                Post::update($postId, $updateData);
            }

            // Delete specified images
            foreach ($deleteImageIds as $imageId) {
                $image = PostImage::getById((int)$imageId);
                if ($image && (int)$image['post_id'] === $postId) {
                    // Delete file from filesystem (image path is absolute)
                    $config = \config();
                    $uploadPath = $config['upload']['path'];
                    $filePath = $uploadPath . '/' . basename($image['file_path']);
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                    PostImage::delete((int)$imageId);
                }
            }

            // Delete specified videos
            foreach ($deleteVideoIds as $videoId) {
                $video = PostVideo::getById((int)$videoId);
                if ($video && (int)$video['post_id'] === $postId) {
                    $config = \config();
                    $uploadPath = $config['upload']['path'];
                    // Delete video file
                    $videoPath = $uploadPath . '/' . basename($video['file_path']);
                    if (file_exists($videoPath)) {
                        @unlink($videoPath);
                    }
                    // Delete thumbnail if exists
                    if ($video['thumbnail_path']) {
                        $thumbPath = $uploadPath . '/' . basename($video['thumbnail_path']);
                        if (file_exists($thumbPath)) {
                            @unlink($thumbPath);
                        }
                    }
                    PostVideo::delete((int)$videoId);
                }
            }

            // Add new images
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

            // Add new videos
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
            
            // Return updated post (pass userId so author can see their own private posts)
            return $this->getPost($postId, $userId);
        } catch (\Throwable $e) {
            Database::rollback();
            Logger::error('post_update_failed', ['error' => $e->getMessage()]);
            throw $e;
        }
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
            'visibility' => $row['visibility'],
            'is_pinned' => (bool)$row['is_pinned'],
            'created_at' => $row['created_at'],
            'author' => [
                'id' => (int)$row['user_id'], 
                'email' => $row['email'],
                'display_name' => $row['display_name'],
                'avatar_path' => $row['avatar_path']
            ],
            'images' => array_map(fn($i) => [
                'id' => (int)$i['id'],
                'file_path' => $i['file_path'], 
                'width' => (int)$i['width'], 
                'height' => (int)$i['height']
            ], $images),
            'videos' => array_map(fn($v) => [
                'id' => (int)$v['id'],
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
