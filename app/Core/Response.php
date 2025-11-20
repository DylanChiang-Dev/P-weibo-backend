<?php
namespace App\Core;

class Response {
    public static function json(array $data, int $status = 200, array $headers = []): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        foreach ($headers as $k => $v) header($k . ': ' . $v);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function setCookie(string $name, string $value, array $opts): void {
        setcookie($name, $value, [
            'expires' => $opts['expires'] ?? 0,
            'path' => $opts['path'] ?? '/',
            'domain' => $opts['domain'] ?? '',
            'secure' => $opts['secure'] ?? true,
            'httponly' => $opts['httponly'] ?? true,
            'samesite' => $opts['samesite'] ?? 'None',
        ]);
    }

    public static function clearCookie(string $name, array $opts = []): void {
        self::setCookie($name, '', [
            'expires' => time() - 3600,
            'path' => $opts['path'] ?? '/',
            'domain' => $opts['domain'] ?? '',
            'secure' => $opts['secure'] ?? true,
            'httponly' => $opts['httponly'] ?? true,
            'samesite' => $opts['samesite'] ?? 'None',
        ]);
    }
}
?>