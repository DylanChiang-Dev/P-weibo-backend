<?php
namespace App\Core;

/**
 * Tiny cache helper: prefers Redis, falls back to file cache in /tmp.
 */
class Cache {
    public static function get(string $key): ?string {
        $redis = RedisClient::get();
        if ($redis) {
            try {
                $v = $redis->get(RedisClient::prefix() . $key);
                return is_string($v) ? $v : null;
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $path = self::filePath($key);
        if (!is_file($path)) return null;
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') return null;
        $data = json_decode($raw, true);
        if (!is_array($data)) return null;
        $exp = (int)($data['exp'] ?? 0);
        if ($exp !== 0 && $exp < time()) {
            @unlink($path);
            return null;
        }
        $val = $data['val'] ?? null;
        return is_string($val) ? $val : null;
    }

    public static function setex(string $key, int $ttlSeconds, string $value): void {
        $ttlSeconds = max(1, $ttlSeconds);

        $redis = RedisClient::get();
        if ($redis) {
            try {
                $redis->setex(RedisClient::prefix() . $key, $ttlSeconds, $value);
                return;
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $path = self::filePath($key);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $data = ['exp' => time() + $ttlSeconds, 'val' => $value];
        @file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function del(string $key): void {
        $redis = RedisClient::get();
        if ($redis) {
            try {
                $redis->del(RedisClient::prefix() . $key);
            } catch (\Throwable $e) {
                // ignore
            }
        }
        @unlink(self::filePath($key));
    }

    private static function filePath(string $key): string {
        return rtrim(sys_get_temp_dir(), '/') . '/pweibo_cache/' . md5($key) . '.json';
    }
}
?>

