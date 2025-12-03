<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Exceptions\NotFoundException;
use App\Exceptions\ForbiddenException;
use App\Models\Media;

class MediaController {
    /**
     * Normalize $_FILES array structure
     * Converts both single and multiple file uploads to a consistent format
     */
    private function normalizeFiles(array $filesData): array {
        // Check if this is array-style upload (files[])
        if (isset($filesData['name']) && is_array($filesData['name'])) {
            // Multiple files with array structure
            $normalized = [];
            $count = count($filesData['name']);
            
            for ($i = 0; $i < $count; $i++) {
                $normalized[] = [
                    'name' => $filesData['name'][$i] ?? '',
                    'type' => $filesData['type'][$i] ?? '',
                    'tmp_name' => $filesData['tmp_name'][$i] ?? '',
                    'error' => $filesData['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $filesData['size'][$i] ?? 0
                ];
            }
            
            return $normalized;
        }
        
        // Single file upload
        if (isset($filesData['name']) && is_string($filesData['name'])) {
            return [$filesData];
        }
        
        // Already normalized or empty
        return $filesData;
    }

    /**
     * Get human-readable upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            default => 'Unknown upload error'
        };
    }
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
        try {
            \App\Core\Logger::info('media_upload_start', [
                'user_id' => $req->user['id'] ?? 'unknown',
                'files_count' => count($req->files['files'] ?? [])
            ]);
            
            $userId = (int)$req->user['id'];
            
            if (empty($req->files['files'])) {
                \App\Core\Logger::warning('no_files_provided');
                throw new \App\Exceptions\ValidationException('No files provided');
            }

            // Normalize files array structure
            $files = $this->normalizeFiles($req->files['files']);
            
            \App\Core\Logger::info('files_normalized', [
                'count' => count($files),
                'structure' => array_map(fn($f) => [
                    'name' => $f['name'] ?? 'unknown',
                    'size' => $f['size'] ?? 0,
                    'error' => $f['error'] ?? -1
                ], $files)
            ]);

            $config = config();
            $uploadPath = $config['upload']['path'];
            $appUrl = rtrim($config['app_url'], '/');
            
            \App\Core\Logger::info('upload_config', [
                'upload_path' => $uploadPath,
                'app_url' => $appUrl,
                'path_exists' => is_dir($uploadPath),
                'path_writable' => is_writable($uploadPath)
            ]);
            
            // Ensure upload directory exists
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0775, true);
            }

            $uploadedMedia = [];
            $errors = [];
            $allowedMimeTypes = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/svg+xml'
            ];

            foreach ($files as $index => $file) {
                try {
                    \App\Core\Logger::info('processing_file', [
                        'index' => $index,
                        'name' => $file['name'] ?? 'unknown',
                        'size' => $file['size'] ?? 0,
                        'error' => $file['error'] ?? -1
                    ]);
                    
                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $errorMsg = $this->getUploadErrorMessage($file['error']);
                        \App\Core\Logger::warning('file_upload_error', [
                            'error_code' => $file['error'],
                            'error_message' => $errorMsg,
                            'filename' => $file['name'] ?? 'unknown'
                        ]);
                        $errors[] = [
                            'filename' => $file['name'] ?? "file_$index",
                            'error' => $errorMsg
                        ];
                        continue;
                    }

                    // Validate MIME type
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mimeType = $finfo->file($file['tmp_name']);
                    
                    if (!in_array($mimeType, $allowedMimeTypes)) {
                        $errorMsg = "Invalid file type: $mimeType. Allowed types: " . implode(', ', $allowedMimeTypes);
                        \App\Core\Logger::warning('invalid_mime_type', [
                            'mime' => $mimeType,
                            'filename' => $file['name']
                        ]);
                        $errors[] = [
                            'filename' => $file['name'] ?? "file_$index",
                            'error' => $errorMsg
                        ];
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
                        $errorMsg = 'Failed to save file to server';
                        \App\Core\Logger::error('move_uploaded_file_failed', [
                            'tmp_name' => $file['tmp_name'],
                            'destination' => $filepath
                        ]);
                        $errors[] = [
                            'filename' => $file['name'] ?? "file_$index",
                            'error' => $errorMsg
                        ];
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
                } catch (\Throwable $e) {
                    \App\Core\Logger::error('file_processing_error', [
                        'filename' => $file['name'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $errors[] = [
                        'filename' => $file['name'] ?? "file_$index",
                        'error' => $e->getMessage()
                    ];
                }
            }

            \App\Core\Logger::info('media_upload_complete', [
                'uploaded_count' => count($uploadedMedia),
                'failed_count' => count($errors)
            ]);

            // If no files were successfully uploaded, throw an exception
            if (empty($uploadedMedia)) {
                if (!empty($errors)) {
                    throw new \App\Exceptions\ValidationException(
                        'All file uploads failed',
                        ['errors' => $errors]
                    );
                } else {
                    throw new \App\Exceptions\ValidationException('No valid files to upload');
                }
            }

            // Return success with uploaded files and any errors
            $response = ['items' => $uploadedMedia];
            if (!empty($errors)) {
                $response['partial_errors'] = $errors;
            }
            
            ApiResponse::success($response, 201);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('media_upload_fatal_error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 在开发环境下返回详细错误信息
            $config = config();
            if ($config['app_env'] === 'development') {
                ApiResponse::error([
                    'message' => 'Media upload failed: ' . $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'type' => get_class($e)
                ], 500);
            } else {
                // 生产环境只返回通用错误
                throw $e;
            }
        }
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
