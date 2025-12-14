<?php
namespace App\Core;

/**
 * Minimal Redis client wrapper (ext-redis).
 * Designed to be optional: if Redis isn't configured/available, callers can fall back gracefully.
 */
class RedisClient {
    private static ?\Redis $redis = null;
    private static bool $attempted = false;

    public static function get(): ?\Redis {
        if (self::$attempted) {
            return self::$redis;
        }
        self::$attempted = true;

        if (!class_exists(\Redis::class)) {
            self::$redis = null;
            return null;
        }

        $config = \config();
        $redisCfg = $config['redis'] ?? [];
        if (!is_array($redisCfg) || !($redisCfg['enabled'] ?? false)) {
            self::$redis = null;
            return null;
        }

        $host = (string)($redisCfg['host'] ?? '127.0.0.1');
        $port = (int)($redisCfg['port'] ?? 6379);
        $db = (int)($redisCfg['db'] ?? 0);
        $password = $redisCfg['password'] ?? null;
        $timeout = (float)($redisCfg['timeout'] ?? 0.2);
        $persistent = (bool)($redisCfg['persistent'] ?? true);

        try {
            $r = new \Redis();
            $ok = $persistent
                ? $r->pconnect($host, $port, $timeout)
                : $r->connect($host, $port, $timeout);

            if (!$ok) {
                self::$redis = null;
                return null;
            }

            if (is_string($password) && $password !== '') {
                $r->auth($password);
            }

            if ($db !== 0) {
                $r->select($db);
            }

            // Fast fail on slow/unreachable Redis
            if (method_exists($r, 'setOption')) {
                $r->setOption(\Redis::OPT_READ_TIMEOUT, $timeout);
            }

            self::$redis = $r;
            return self::$redis;
        } catch (\Throwable $e) {
            // Logger is safe to call even if not initialized (it no-ops).
            Logger::warn('redis_connect_failed', ['error' => $e->getMessage()]);
            self::$redis = null;
            return null;
        }
    }

    public static function prefix(): string {
        $config = \config();
        $redisCfg = $config['redis'] ?? [];
        $prefix = is_array($redisCfg) ? (string)($redisCfg['prefix'] ?? 'pweibo:') : 'pweibo:';
        return $prefix;
    }
}
?>

