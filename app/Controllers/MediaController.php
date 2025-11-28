<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use App\Models\Media;

class MediaController {
    /**
     * Get user's media list with pagination
     */
    public function list(Request $req): void {
        $userId = (int)$req->user['id'];
        
        // Parse pagination parameters
        $page = max(1, (int)($req->query['page'] ?? 1));
        $limit = min((int)($req->query['limit'] ?? 50), 200);
        $offset = ($page - 1) * $limit;
        
        // Get media and total count
        $items = Media::listByUser($userId, $limit, $offset);
        $total = Media::countByUser($userId);
        $totalPages = (int)ceil($total / $limit);
        
        ApiResponse::success([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ]);
    }

    /**
     * Upload media files
     */
    public function upload(Request $req): void {
        $userId = (int)$req->user['id'];
        
        if (empty($req->files['files'])) {
            throw new \App\Exceptions\ValidationException('No files provided');
        }

        $files = $req->files['files'];
        // Handle single file or multiple files
        if (!isset($files[0])) {
            $files = [$files];
        }

        $uploadedMedia = [];
        $mediaService = new \App\Services\MediaService();
        $config = config();
        $uploadPath = $config['upload']['path'];

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            // Upload file
            $result = $mediaService->uploadImage($file);
            
            if ($result) {
                // Create media record
                $mediaId = Media::create([
                    'user_id' => $userId,
                    'url' => $result['url'],
                    'filename' => $result['filename'],
                    'filepath' => $result['path'],
                    'size' => $file['size'],
                    'mime_type' => $file['type']
                ]);

                $uploadedMedia[] = [
                    'id' => $mediaId,
                    'url' => $result['url'],
                    'filename' => $result['filename'],
                    'size' => $file['size'],
                    'mime_type' => $file['type'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }

        ApiResponse::success([
            'items' => $uploadedMedia
        ], 201);
    }

    /**
     * Delete media
     */
    public function delete(Request $req, array $params): void {
        $mediaId = (int)($params['id'] ?? 0);
        $userId = (int)$req->user['id'];
        
        // Get media
        $media = Media::getById($mediaId);
        
        if (!$media) {
            throw new NotFoundException('Media not found');
        }

        // Check ownership
        if ((int)$media['user_id'] !== $userId) {
            throw new ForbiddenException('You can only delete your own media');
        }

        // Delete physical file
        $filepath = $media['filepath'];
        if ($filepath && file_exists($filepath)) {
            unlink($filepath);
        }

        // Delete database record
        Media::delete($mediaId);

        ApiResponse::success([
            'message' => 'Media deleted successfully'
        ]);
    }
}
?>
