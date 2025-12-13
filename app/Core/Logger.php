<?php
namespace App\Core;

class Logger {
    private static ?string $logDir = null;

    public static function init(string $logPath): void {
        self::$logDir = rtrim($logPath, '/');
        if (!is_dir(self::$logDir)) {
            @mkdir(self::$logDir, 0775, true);
        }
    }

    private static function write(string $level, string $event, array $context = []): void {
        if (self::$logDir === null || self::$logDir === '') {
            return;
        }

        $file = self::$logDir . '/' . date('Y-m-d') . '.log';
        $record = [
            'ts' => date('c'),
            'level' => $level,
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ] + $context;
        $line = json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $event, array $context = []): void { self::write('info', $event, $context); }
    public static function warn(string $event, array $context = []): void { self::write('warn', $event, $context); }
    public static function error(string $event, array $context = []): void { self::write('error', $event, $context); }
}
?>
