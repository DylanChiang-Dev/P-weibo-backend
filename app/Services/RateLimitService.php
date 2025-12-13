<?php
namespace App\Services;

class RateLimitService {
    private string $dir;
    private int $max;
    private int $window;

    /**
     * Lightweight file-based rate limiter for middleware usage.
     * Stores counters in sys temp dir to avoid requiring app config wiring.
     */
    public static function attempt(string $key, int $maxAttempts = 60, int $decayMinutes = 1): bool {
        $dir = rtrim(sys_get_temp_dir(), '/') . '/pweibo_ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/' . md5($key) . '.json';
        $now = time();
        $windowSeconds = max(1, $decayMinutes) * 60;

        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            // Fail-open to avoid taking down the request path if filesystem is unavailable.
            return true;
        }

        try {
            @flock($fh, LOCK_EX);
            $content = stream_get_contents($fh);
            $data = is_string($content) && $content !== '' ? (json_decode($content, true) ?: null) : null;
            if (!is_array($data)) {
                $data = ['start' => $now, 'count' => 0];
            }

            if ($now - (int)($data['start'] ?? $now) > $windowSeconds) {
                $data['start'] = $now;
                $data['count'] = 0;
            }

            $data['count'] = (int)($data['count'] ?? 0) + 1;

            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, json_encode($data, JSON_UNESCAPED_UNICODE));
        } finally {
            @flock($fh, LOCK_UN);
            fclose($fh);
        }

        return (int)$data['count'] <= $maxAttempts;
    }

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
