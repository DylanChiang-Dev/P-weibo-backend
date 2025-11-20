<?php
namespace App\Services;

class RateLimitService {
    private string $dir;
    private int $max;
    private int $window;

    public function __construct(string $dir, int $max, int $windowSeconds) {
        $this->dir = rtrim($dir, '/');
        $this->max = $max;
        $this->window = $windowSeconds;
        if (!is_dir($this->dir)) @mkdir($this->dir, 0775, true);
    }

    public function check(string $key): bool {
        $file = $this->dir . '/' . md5($key) . '.cache';
        $now = time();
        $data = ['start' => $now, 'count' => 0];
        if (file_exists($file)) {
            $content = file_get_contents($file);
            if ($content) $data = json_decode($content, true) ?: $data;
        }
        if ($now - ($data['start'] ?? $now) > $this->window) {
            $data['start'] = $now;
            $data['count'] = 0;
        }
        $data['count']++;
        file_put_contents($file, json_encode($data));
        return $data['count'] <= $this->max;
    }
}
?>