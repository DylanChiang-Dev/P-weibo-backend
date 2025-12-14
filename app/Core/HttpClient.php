<?php
namespace App\Core;

class HttpClient {
    public static function get(string $url, array $headers = []): array {
        return self::request('GET', $url, null, $headers);
    }

    public static function post(string $url, ?string $body, array $headers = []): array {
        return self::request('POST', $url, $body, $headers);
    }

    private static function request(string $method, string $url, ?string $body, array $headers): array {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => null];
        }

        $headerLines = [];
        foreach ($headers as $k => $v) {
            $headerLines[] = $k . ': ' . $v;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => $headerLines,
        ]);

        if ($method === 'POST' && $body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $respBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($respBody)) {
            return ['ok' => false, 'status' => $status, 'body' => null];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'body' => $respBody,
        ];
    }

    public static function json(array $resp): ?array {
        $body = $resp['body'] ?? null;
        if (!is_string($body) || $body === '') return null;
        $data = json_decode($body, true);
        return is_array($data) ? $data : null;
    }
}
?>

