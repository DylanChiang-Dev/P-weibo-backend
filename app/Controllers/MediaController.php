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

        $config = config();
        $uploadPath = $config['upload']['path'];
        $appUrl = rtrim($config['app_url'], '/');
        
        // Ensure upload directory exists
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0775, true);
        }

        $uploadedMedia = [];
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml'
        ];

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                \App\Core\Logger::warning('file_upload_error', [
                    'error_code' => $file['error'],
                    'filename' => $file['name'] ?? 'unknown'
                ]);
                continue;
            }

            // Validate MIME type
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            
            if (!in_array($mimeType, $allowedMimeTypes)) {
                \App\Core\Logger::warning('invalid_mime_type', [
                    'mime' => $mimeType,
                    'filename' => $file['name']
                ]);
                continue;
            }

            // Generate unique filename
            $extension = match($mimeType) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp',
                'image/svg+xml' => 'svg',
                default => 'jpg'
            };
            
            $uniqueName = bin2hex(random_bytes(16)) . '.' . $extension;
            $filepath = $uploadPath . '/' . $uniqueName;
            $url = $appUrl . '/uploads/' . $uniqueName;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                \App\Core\Logger::error('move_uploaded_file_failed', [
                    'tmp_name' => $file['tmp_name'],
                    'destination' => $filepath
                ]);
                continue;
            }

            @chmod($filepath, 0644);

            // Create media record
            $mediaId = Media::create([
                'user_id' => $userId,
                'url' => $url,
                'filename' => $file['name'],
                'filepath' => $filepath,
                'size' => $file['size'],
                'mime_type' => $mimeType
            ]);

            $uploadedMedia[] = [
                'id' => $mediaId,
                'url' => $url,
                'filename' => $file['name'],
                'size' => $file['size'],
                'mime_type' => $mimeType,
                'created_at' => date('Y-m-d H:i:s')
            ];

            \App\Core\Logger::info('media_uploaded', [
                'id' => $mediaId,
                'filename' => $file['name'],
                'size' => $file['size']
            ]);
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
