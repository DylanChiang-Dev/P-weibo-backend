<?php
namespace App\Core;

class Request {
    public string $method;
    public string $path;
    public array $headers;
    public array $query;
    public array $cookies;
    public array $files;
    public mixed $body;
    public ?array $user = null;

    public static function fromGlobals(): self {
        $r = new self();
        $r->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $r->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $r->headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $r->query = $_GET ?? [];
        $r->cookies = $_COOKIE ?? [];
        $r->files = $_FILES ?? [];
        $r->body = self::parseBody($r->headers);
        return $r;
    }

    private static function parseBody(array $headers): mixed {
        $ct = $headers['Content-Type'] ?? $headers['content-type'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $raw = file_get_contents('php://input');
            if ($raw === false || $raw === '') return null;
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        }
        // 對於 form-data 或 x-www-form-urlencoded 使用 $_POST
        return $_POST ?? null;
    }

    public function bearerToken(): ?string {
        $auth = $this->headers['Authorization'] ?? $this->headers['authorization'] ?? null;
        if (!$auth) return null;
        if (stripos($auth, 'Bearer ') === 0) return trim(substr($auth, 7));
        return null;
    }

    public function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }
    public function userAgent(): string { return $_SERVER['HTTP_USER_AGENT'] ?? ''; }
}
?>