<?php
namespace App\Controllers;

use App\Core\ApiResponse;
use App\Core\HttpClient;
use App\Core\Request;
use App\Exceptions\ValidationException;
use App\Services\IgdbService;
use App\Services\IntegrationService;

class SearchController {
    private IntegrationService $integrationService;
    private IgdbService $igdbService;

    public function __construct() {
        $this->integrationService = new IntegrationService();
        $this->igdbService = new IgdbService();
    }

    public function igdb(Request $req): void {
        $userId = (int)($req->user['id'] ?? 0);
        $query = trim((string)($req->query['query'] ?? ''));
        if ($query === '') {
            throw new ValidationException('Missing query');
        }

        $limit = (int)($req->query['limit'] ?? 20);
        $limit = max(1, min(50, $limit));

        // Exact match: IGDB URL or numeric ID
        if (ctype_digit($query)) {
            $game = $this->igdbService->getGameById($userId, (int)$query);
            if ($game) {
                ApiResponse::success([$game]);
            }
        }

        if (preg_match('~(?:https?://)?(?:www\\.)?igdb\\.com/games/([^/?#]+)~i', $query, $m)) {
            $slug = trim((string)($m[1] ?? ''));
            if ($slug !== '') {
                $game = $this->igdbService->getGameBySlug($userId, $slug);
                if ($game) {
                    ApiResponse::success([$game]);
                }
            }
        }

        $data = $this->igdbService->search($userId, $query, $limit);
        ApiResponse::success($data);
    }

    public function tmdb(Request $req): void {
        $userId = (int)($req->user['id'] ?? 0);
        $query = trim((string)($req->query['query'] ?? ''));
        $type = (string)($req->query['type'] ?? 'movie');
        if ($query === '') {
            throw new ValidationException('Missing query');
        }
        if (!in_array($type, ['movie', 'tv'], true)) {
            throw new ValidationException('Invalid type');
        }

        $creds = $this->integrationService->getCredentials($userId);
        $apiKey = trim((string)($creds['tmdb_api_key'] ?? ''));
        if ($apiKey === '') {
            throw new ValidationException('TMDB not configured');
        }

        $url = 'https://api.themoviedb.org/3/search/' . $type
            . '?api_key=' . rawurlencode($apiKey)
            . '&query=' . rawurlencode($query)
            . '&include_adult=false';

        $resp = HttpClient::get($url, ['Accept' => 'application/json']);
        $data = HttpClient::json($resp);
        if (!is_array($data)) {
            throw new ValidationException('TMDB upstream error');
        }
        ApiResponse::success($data);
    }

    public function rawg(Request $req): void {
        $userId = (int)($req->user['id'] ?? 0);
        $query = trim((string)($req->query['query'] ?? ''));
        if ($query === '') {
            throw new ValidationException('Missing query');
        }

        $creds = $this->integrationService->getCredentials($userId);
        $apiKey = trim((string)($creds['rawg_api_key'] ?? ''));
        if ($apiKey === '') {
            throw new ValidationException('RAWG not configured');
        }

        $url = 'https://api.rawg.io/api/games'
            . '?key=' . rawurlencode($apiKey)
            . '&search=' . rawurlencode($query)
            . '&page_size=20';

        $resp = HttpClient::get($url, ['Accept' => 'application/json']);
        $data = HttpClient::json($resp);
        if (!is_array($data)) {
            throw new ValidationException('RAWG upstream error');
        }
        ApiResponse::success($data);
    }

    public function googleBooks(Request $req): void {
        $userId = (int)($req->user['id'] ?? 0);
        $query = trim((string)($req->query['query'] ?? ''));
        if ($query === '') {
            throw new ValidationException('Missing query');
        }

        $creds = $this->integrationService->getCredentials($userId);
        $apiKey = trim((string)($creds['google_books_api_key'] ?? ''));

        $url = 'https://www.googleapis.com/books/v1/volumes?q=' . rawurlencode($query) . '&maxResults=20';
        if ($apiKey !== '') {
            $url .= '&key=' . rawurlencode($apiKey);
        }

        $resp = HttpClient::get($url, ['Accept' => 'application/json']);
        $data = HttpClient::json($resp);
        if (!is_array($data)) {
            throw new ValidationException('Google Books upstream error');
        }
        ApiResponse::success($data);
    }
}
?>
