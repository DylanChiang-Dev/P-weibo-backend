<?php
namespace App\Services;

use App\Core\Logger;
use App\Exceptions\ValidationException;

class MediaService {
    private string $uploadPath;
    private int $maxBytes;
    private int $ffmpegTimeoutSeconds;
    private array $allowedMimeTypes = [
        'video/mp4',
        'video/webm',
        'video/quicktime', // .mov
        'video/x-msvideo', // .avi
    ];

    public function __construct(string $uploadPath, int $maxMb, int $ffmpegTimeoutSeconds = 5) {
        $this->uploadPath = rtrim($uploadPath, '/');
        $this->maxBytes = $maxMb * 1024 * 1024;
        $this->ffmpegTimeoutSeconds = max(1, $ffmpegTimeoutSeconds);
        if (!is_dir($this->uploadPath)) @mkdir($this->uploadPath, 0775, true);
    }

    /**
     * Process uploaded video file
     * 
     * @param array $file Uploaded file array from $_FILES
     * @return array ['file_path', 'thumbnail_path', 'duration', 'file_size', 'mime_type']
     * @throws ValidationException on validation errors
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
            throw new ValidationException('Upload error: ' . $errorMsg);
        }

        if (!isset($file['tmp_name']) || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException('Invalid upload source');
        }

        $fileSize = $file['size'] ?? 0;
        if ($fileSize > $this->maxBytes) {
            Logger::error('file_too_large', [
                'size' => $fileSize,
                'max' => $this->maxBytes
            ]);
            throw new ValidationException('File too large. Max: ' . ($this->maxBytes / 1024 / 1024) . 'MB');
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
            throw new ValidationException('Invalid video format. Allowed: MP4, WebM, MOV, AVI');
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
            throw new ValidationException('Failed to save video file');
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
        if (!function_exists('proc_open')) {
            return null;
        }

        try {
            $ffprobe = $this->findBinary('ffprobe');
            if (!$ffprobe) {
                return null;
            }

            $output = $this->runCommand(
                [
                    $ffprobe,
                    '-v', 'error',
                    '-show_entries', 'format=duration',
                    '-of', 'default=noprint_wrappers=1:nokey=1',
                    $videoPath,
                ],
                $this->ffmpegTimeoutSeconds
            );
            if ($output && is_numeric(trim($output))) {
                return (int)round((float)trim($output));
            }
        } catch (\Throwable $e) {
            Logger::warn('ffmpeg_duration_failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Generate video thumbnail using FFmpeg (if available)
     */
    private function generateThumbnail(string $videoPath, string $base): ?string {
        if (!function_exists('proc_open')) {
            return null;
        }

        try {
            // Check if FFmpeg is available
            $ffmpeg = $this->findBinary('ffmpeg');
            if (!$ffmpeg) {
                return null;
            }

            $thumbnailPath = $this->uploadPath . '/' . $base . '_thumb.jpg';
            
            // Extract frame at 1 second (or 00:00:01)
            $this->runCommand(
                [
                    $ffmpeg,
                    '-y',
                    '-i', $videoPath,
                    '-ss', '00:00:01',
                    '-vframes', '1',
                    $thumbnailPath,
                ],
                $this->ffmpegTimeoutSeconds
            );
            
            if (file_exists($thumbnailPath) && filesize($thumbnailPath) > 0) {
                return $thumbnailPath;
            }
            if (file_exists($thumbnailPath)) {
                @unlink($thumbnailPath);
            }
        } catch (\Throwable $e) {
            Logger::warn('ffmpeg_thumbnail_failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    private function findBinary(string $name): ?string {
        if (!function_exists('proc_open')) {
            return null;
        }

        $out = $this->runCommand(['which', $name], 1);
        $path = $out ? trim($out) : '';
        return $path !== '' ? $path : null;
    }

    private function runCommand(array $cmd, int $timeoutSeconds): ?string {
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }

        $start = microtime(true);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $maxBytes = 1024 * 1024; // 1MB output cap

        try {
            while (true) {
                $status = proc_get_status($process);
                if (!is_array($status) || !($status['running'] ?? false)) {
                    break;
                }

                $elapsed = microtime(true) - $start;
                if ($elapsed > $timeoutSeconds) {
                    @proc_terminate($process, 9);
                    Logger::warn('command_timeout', ['cmd' => $cmd[0] ?? 'unknown', 'timeout' => $timeoutSeconds]);
                    return null;
                }

                $read = [$pipes[1], $pipes[2]];
                $write = null;
                $except = null;
                @stream_select($read, $write, $except, 0, 200000);

                foreach ($read as $r) {
                    $chunk = @fread($r, 8192);
                    if ($chunk === false || $chunk === '') {
                        continue;
                    }
                    if ($r === $pipes[1]) {
                        $stdout .= $chunk;
                        if (strlen($stdout) > $maxBytes) {
                            $stdout = substr($stdout, 0, $maxBytes);
                        }
                    } else {
                        $stderr .= $chunk;
                        if (strlen($stderr) > $maxBytes) {
                            $stderr = substr($stderr, 0, $maxBytes);
                        }
                    }
                }
            }

            // Drain remaining output
            $stdout .= (string)@stream_get_contents($pipes[1]);
            $stderr .= (string)@stream_get_contents($pipes[2]);
        } finally {
            foreach ($pipes as $p) {
                if (is_resource($p)) {
                    fclose($p);
                }
            }
            @proc_close($process);
        }

        if ($stderr !== '') {
            // Don't throw; just provide signal for debugging.
            Logger::info('command_stderr', ['cmd' => $cmd[0] ?? 'unknown', 'stderr' => substr($stderr, 0, 2000)]);
        }

        return $stdout;
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
