<?php
namespace App\Services;

use App\Core\Logger;

class MediaService {
    private string $uploadPath;
    private int $maxBytes;
    private array $allowedMimeTypes = [
        'video/mp4',
        'video/webm',
        'video/quicktime', // .mov
        'video/x-msvideo', // .avi
    ];

    public function __construct(string $uploadPath, int $maxMb) {
        $this->uploadPath = rtrim($uploadPath, '/');
        $this->maxBytes = $maxMb * 1024 * 1024;
        if (!is_dir($this->uploadPath)) @mkdir($this->uploadPath, 0775, true);
    }

    /**
     * Process uploaded video file
     * 
     * @param array $file Uploaded file array from $_FILES
     * @return array ['file_path', 'thumbnail_path', 'duration', 'file_size', 'mime_type']
     * @throws \RuntimeException on validation or processing errors
     */
    public function process(array $file): array {
        Logger::info('media_service_process_start', [
            'file_name' => $file['name'] ?? 'unknown',
            'file_size' => $file['size'] ?? 0,
            'upload_path' => $this->uploadPath,
            'path_exists' => is_dir($this->uploadPath),
            'path_writable' => is_writable($this->uploadPath)
        ]);
        
        // Validate upload
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errorMsg = $this->getUploadErrorMessage($file['error']);
            Logger::error('upload_error', [
                'error' => $errorMsg,
                'code' => $file['error']
            ]);
            throw new \RuntimeException('Upload error: ' . $errorMsg);
        }

        $fileSize = $file['size'] ?? 0;
        if ($fileSize > $this->maxBytes) {
            Logger::error('file_too_large', [
                'size' => $fileSize,
                'max' => $this->maxBytes
            ]);
            throw new \RuntimeException('File too large. Max: ' . ($this->maxBytes / 1024 / 1024) . 'MB');
        }

        // Validate MIME type
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        Logger::info('file_mime_detected', [
            'mime' => $mime,
            'allowed' => $this->allowedMimeTypes
        ]);
        
        if (!in_array($mime, $this->allowedMimeTypes, true)) {
            Logger::error('invalid_mime_type', [
                'mime' => $mime,
                'allowed' => $this->allowedMimeTypes
            ]);
            throw new \RuntimeException('Invalid video format. Allowed: MP4, WebM, MOV, AVI');
        }

        // Generate unique filename
        $ext = $this->getExtensionFromMime($mime);
        $base = bin2hex(random_bytes(16));
        $videoPath = $this->uploadPath . '/' . $base . '.' . $ext;

        Logger::info('moving_uploaded_file', [
            'from' => $file['tmp_name'],
            'to' => $videoPath,
            'tmp_exists' => file_exists($file['tmp_name']),
            'dest_dir_writable' => is_writable($this->uploadPath)
        ]);

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $videoPath)) {
            Logger::error('move_uploaded_file_failed', [
                'tmp_name' => $file['tmp_name'],
                'destination' => $videoPath,
                'tmp_exists' => file_exists($file['tmp_name']),
                'dir_writable' => is_writable($this->uploadPath),
                'dir_exists' => is_dir($this->uploadPath)
            ]);
            throw new \RuntimeException('Failed to save video file');
        }

        @chmod($videoPath, 0644);
        Logger::info('video_file_saved', [
            'path' => $videoPath,
            'size' => filesize($videoPath)
        ]);

        // Try to extract video metadata (duration) if FFmpeg is available
        $duration = $this->extractDuration($videoPath);
        
        // Try to generate thumbnail if FFmpeg is available
        $thumbnailPath = $this->generateThumbnail($videoPath, $base);

        return [
            'file_path' => $videoPath,
            'thumbnail_path' => $thumbnailPath,
            'duration' => $duration,
            'file_size' => $fileSize,
            'mime_type' => $mime,
        ];
    }

    /**
     * Extract video duration using FFmpeg (if available)
     */
    private function extractDuration(string $videoPath): ?int {
        // Check if FFmpeg is available
        $ffprobe = shell_exec('which ffprobe 2>/dev/null');
        if (!$ffprobe) {
            return null;
        }

        $cmd = sprintf(
            'ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($videoPath)
        );
        
        $output = shell_exec($cmd);
        if ($output && is_numeric(trim($output))) {
            return (int)round((float)trim($output));
        }

        return null;
    }

    /**
     * Generate video thumbnail using FFmpeg (if available)
     */
    private function generateThumbnail(string $videoPath, string $base): ?string {
        // Check if FFmpeg is available
        $ffmpeg = shell_exec('which ffmpeg 2>/dev/null');
        if (!$ffmpeg) {
            return null;
        }

        $thumbnailPath = $this->uploadPath . '/' . $base . '_thumb.jpg';
        
        // Extract frame at 1 second
        $cmd = sprintf(
            'ffmpeg -i %s -ss 00:00:01.000 -vframes 1 -vf scale=320:-1 %s 2>/dev/null',
            escapeshellarg($videoPath),
            escapeshellarg($thumbnailPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($thumbnailPath)) {
            @chmod($thumbnailPath, 0644);
            return $thumbnailPath;
        }

        return null;
    }

    /**
     * Get file extension from MIME type
     */
    private function getExtensionFromMime(string $mime): string {
        return match ($mime) {
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            default => 'video',
        };
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
            default => 'Unknown upload error',
        };
    }
}
?>
