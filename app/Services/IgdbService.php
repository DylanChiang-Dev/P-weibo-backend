<?php
namespace App\Services;

use App\Core\Cache;
use App\Core\HttpClient;
use App\Exceptions\ValidationException;

class IgdbService {
    private IntegrationService $integrationService;

    public function __construct() {
        $this->integrationService = new IntegrationService();
    }

    public function search(int $userId, string $query, int $limit = 20): array {
        $limit = max(1, min(50, $limit));
        $creds = $this->integrationService->getCredentials($userId);
        $clientId = trim((string)($creds['igdb_client_id'] ?? ''));
        $clientSecret = trim((string)($creds['igdb_client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            throw new ValidationException('IGDB not configured');
        }

        $token = $this->getAccessToken($userId, $clientId, $clientSecret);

        $body = 'search "' . $this->escapeIgdb($query) . "\";\n"
            . "fields id, name, slug, cover.image_id, first_release_date, total_rating, summary, platforms.name;\n"
            . "limit " . $limit . ';';

        return $this->queryGames($userId, $clientId, $clientSecret, $token, $body);
    }

    public function getGameById(int $userId, int $id): ?array {
        if ($id <= 0) return null;

        $creds = $this->integrationService->getCredentials($userId);
        $clientId = trim((string)($creds['igdb_client_id'] ?? ''));
        $clientSecret = trim((string)($creds['igdb_client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            throw new ValidationException('IGDB not configured');
        }

        $token = $this->getAccessToken($userId, $clientId, $clientSecret);

        $body = "fields id, name, slug, cover.image_id, first_release_date, total_rating, summary, platforms.name;\n"
            . 'where id = ' . $id . ";\n"
            . 'limit 1;';

        $data = $this->queryGames($userId, $clientId, $clientSecret, $token, $body);
        return isset($data[0]) && is_array($data[0]) ? $data[0] : null;
    }

    public function getGameBySlug(int $userId, string $slug): ?array {
        $slug = trim($slug);
        if ($slug === '') return null;

        $creds = $this->integrationService->getCredentials($userId);
        $clientId = trim((string)($creds['igdb_client_id'] ?? ''));
        $clientSecret = trim((string)($creds['igdb_client_secret'] ?? ''));
        if ($clientId === '' || $clientSecret === '') {
            throw new ValidationException('IGDB not configured');
        }

        $token = $this->getAccessToken($userId, $clientId, $clientSecret);

        $body = "fields id, name, slug, cover.image_id, first_release_date, total_rating, summary, platforms.name;\n"
            . 'where slug = "' . $this->escapeIgdb($slug) . "\";\n"
            . 'limit 1;';

        $data = $this->queryGames($userId, $clientId, $clientSecret, $token, $body);
        return isset($data[0]) && is_array($data[0]) ? $data[0] : null;
    }

    private function queryGames(int $userId, string $clientId, string $clientSecret, string $token, string $body): array {
        $resp = HttpClient::post('https://api.igdb.com/v4/games', $body, [
            'Client-ID' => $clientId,
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'text/plain',
        ]);

        // If token expired, refresh and retry once.
        if ((int)($resp['status'] ?? 0) === 401) {
            $this->invalidateToken($userId);
            $token = $this->getAccessToken($userId, $clientId, $clientSecret);
            $resp = HttpClient::post('https://api.igdb.com/v4/games', $body, [
                'Client-ID' => $clientId,
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'text/plain',
            ]);
        }

        $data = HttpClient::json($resp);
        if (!is_array($data)) {
            throw new ValidationException('IGDB upstream error');
        }
        return $data;
    }

    private function getAccessToken(int $userId, string $clientId, string $clientSecret): string {
        $cacheKey = 'igdb:token:' . $userId;
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $url = 'https://id.twitch.tv/oauth2/token'
            . '?client_id=' . rawurlencode($clientId)
            . '&client_secret=' . rawurlencode($clientSecret)
            . '&grant_type=client_credentials';

        $resp = HttpClient::post($url, '', [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        $json = HttpClient::json($resp);
        $token = is_array($json) ? (string)($json['access_token'] ?? '') : '';
        $expiresIn = is_array($json) ? (int)($json['expires_in'] ?? 0) : 0;

        if ($token === '' || $expiresIn <= 0) {
            throw new ValidationException('Twitch token error');
        }

        $ttl = max(60, $expiresIn - 60);
        Cache::setex($cacheKey, $ttl, $token);
        return $token;
    }

    private function invalidateToken(int $userId): void {
        Cache::del('igdb:token:' . $userId);
    }

    private function escapeIgdb(string $q): string {
        // IGDB query language uses double quotes; keep it simple.
        $q = str_replace("\\", "\\\\", $q);
        $q = str_replace("\"", "\\\"", $q);
        return $q;
    }
}
?>
