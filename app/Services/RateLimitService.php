<?php
namespace App\Services;

use App\Core\RedisClient;

class RateLimitService {
    private string $dir;
    private int $max;
    private int $window;

    /**
     * Lightweight file-based rate limiter for middleware usage.
     * Stores counters in sys temp dir to avoid requiring app config wiring.
     */
    public static function attempt(string $key, int $maxAttempts = 60, int $decayMinutes = 1): bool {
        $windowSeconds = max(1, $decayMinutes) * 60;
        $redis = RedisClient::get();
        if ($redis) {
            $res = self::attemptRedis($redis, $key, $maxAttempts, $windowSeconds);
            if ($res !== null) {
                return $res;
            }
        }

        return self::attemptFile(sys_get_temp_dir() . '/pweibo_ratelimit', $key, $maxAttempts, $windowSeconds);
    }

    public function __construct(string $dir, int $max, int $windowSeconds) {
        $this->dir = rtrim($dir, '/');
        $this->max = $max;
        $this->window = $windowSeconds;
        if (!is_dir($this->dir)) @mkdir($this->dir, 0775, true);
    }

    public function check(string $key): bool {
        // Prefer Redis if enabled/available; fall back to legacy file-based limiter.
        $redis = RedisClient::get();
        if ($redis) {
            $res = self::attemptRedis($redis, 'legacy:' . $key, $this->max, $this->window);
            if ($res !== null) {
                return $res;
            }
        }

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

    private static function attemptRedis(\Redis $redis, string $key, int $maxAttempts, int $windowSeconds): ?bool {
        $redisKey = RedisClient::prefix() . 'ratelimit:' . $key;
        try {
            $script = 'local current = redis.call("INCR", KEYS[1]); if current == 1 then redis.call("EXPIRE", KEYS[1], ARGV[1]); end; return current;';
            $count = $redis->eval($script, [$redisKey, (int)$windowSeconds], 1);
            return (int)$count <= $maxAttempts;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function attemptFile(string $dir, string $key, int $maxAttempts, int $windowSeconds): bool {
        $dir = rtrim($dir, '/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/' . md5($key) . '.json';
        $now = time();

        $fh = @fopen($file, 'c+');
        if ($fh === false) {
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
}
?>
