<?php
namespace App\Services;

use App\Core\Logger;
use App\Exceptions\ValidationException;

class ImageService {
    private string $uploadPath;
    private int $maxBytes;

    public function __construct(string $uploadPath, int $maxMb) {
        $this->uploadPath = rtrim($uploadPath, '/');
        $this->maxBytes = $maxMb * 1024 * 1024;
        if (!is_dir($this->uploadPath)) @mkdir($this->uploadPath, 0775, true);
    }

    public function process(array $file): array {
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new ValidationException('Upload error');
        if (($file['size'] ?? 0) > $this->maxBytes) throw new ValidationException('File too large');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) throw new ValidationException('Invalid image format');
        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
        $base = bin2hex(random_bytes(16));
        $originalPath = $this->uploadPath . '/' . $base . '_orig.' . $ext;
        $mediumPath   = $this->uploadPath . '/' . $base . '_med.' . $ext;
        $thumbPath    = $this->uploadPath . '/' . $base . '_thumb.' . $ext;

        [$im, $w, $h] = $this->createImage($file['tmp_name'], $mime);
        $this->saveImage($im, $mime, $originalPath);
        // 生成中等與縮圖
        $this->saveResized($im, $w, $h, 800, $mime, $mediumPath);
        $this->saveResized($im, $w, $h, 200, $mime, $thumbPath);
        imagedestroy($im);
        return [
            'original' => $originalPath,
            'medium' => $mediumPath,
            'thumbnail' => $thumbPath,
            'width' => $w,
            'height' => $h,
        ];
    }

    private function createImage(string $tmp, string $mime): array {
        switch ($mime) {
            case 'image/jpeg': $im = @imagecreatefromjpeg($tmp); break;
            case 'image/png':  $im = @imagecreatefrompng($tmp); break;
            case 'image/webp': $im = @imagecreatefromwebp($tmp); break;
            default: throw new ValidationException('Unsupported image format');
        }
        if (!$im) throw new ValidationException('Image decode failed');
        return [$im, imagesx($im), imagesy($im)];
    }

    private function saveImage($im, string $mime, string $path): void {
        switch ($mime) {
            case 'image/jpeg': imagejpeg($im, $path, 90); break;
            case 'image/png':  imagepng($im, $path); break;
            case 'image/webp': imagewebp($im, $path, 90); break;
        }
        @chmod($path, 0644);
    }

    private function saveResized($im, int $w, int $h, int $target, string $mime, string $path): void {
        $ratio = $w / $h;
        $nw = $target; $nh = (int)round($target / $ratio);
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $this->saveImage($dst, $mime, $path);
        imagedestroy($dst);
    }
}
?>
