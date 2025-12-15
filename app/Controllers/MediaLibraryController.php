<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Core\Validator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\InternalServerErrorException;
use App\Models\UserMovie;
use App\Models\UserTvShow;
use App\Models\UserBook;
use App\Models\UserGame;
use App\Models\UserPodcast;
use App\Models\UserDocumentary;
use App\Models\UserAnime;

class MediaLibraryController {
    /**
     * Get user ID from request (defaults to 1 for public access)
     */
    private function getUserId(Request $req): int {
        return isset($req->user) ? (int)$req->user['id'] : 1;
    }

    /**
     * Parse and clamp pagination limit.
     * - Default stays the same (typically 20)
     * - Public (unauthenticated) requests keep a conservative cap
     * - Authenticated requests (admin/personal library) allow larger batches
     */
    private function getLimit(Request $req, int $default = 20): int {
        $raw = isset($req->query['limit']) ? (int)$req->query['limit'] : $default;
        $limit = max(1, $raw);
        $max = isset($req->user) ? 1000 : 100;
        return min($limit, $max);
    }
    
    /**
     * Format date string to MySQL DATE format (YYYY-MM-DD)
     * Handles ISO 8601 format (2024-01-01T00:00:00Z) and standard format
     */
    private function formatDate(?string $date): ?string {
        if (empty($date)) {
            return null;
        }
        // Handle ISO 8601 format
        if (strpos($date, 'T') !== false) {
            $timestamp = strtotime($date);
            return $timestamp ? date('Y-m-d', $timestamp) : null;
        }
        // Already in correct format or other format
        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
    
    /**
     * List movies
     */
    public function listMovies(Request $req): void {
        $userId = $this->getUserId($req);
        $status = $req->query['status'] ?? null;
        $limit = $this->getLimit($req, 20);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $search = $req->query['search'] ?? null;
        $sort = $req->query['sort'] ?? 'date_desc';
        
        $items = UserMovie::list($userId, $status, $limit, $offset, $search, $sort);
        $total = UserMovie::count($userId, $status, $search);
        
        ApiResponse::success([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ]);
    }
    
    /**
     * Add movie
     */
    public function addMovie(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        
        $errs = Validator::required($data, ['tmdb_id']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);
        
        $userId = $this->getUserId($req);
        
        // Check if already exists
        if (UserMovie::exists($userId, (int)$data['tmdb_id'])) {
            throw new ValidationException('Movie already in library');
        }
        
        $movieData = [
            'user_id' => $userId,
            'tmdb_id' => (int)$data['tmdb_id'],
            // Metadata fields
            'title' => $data['title'] ?? null,
            'original_title' => $data['original_title'] ?? null,
            'cover_image_cdn' => $data['cover_image_cdn'] ?? null,
            'overview' => $data['overview'] ?? null,
            'genres' => isset($data['genres']) ? json_encode($data['genres']) : null,
            'external_rating' => isset($data['external_rating']) ? (float)$data['external_rating'] : null,
            'backdrop_image_cdn' => $data['backdrop_image_cdn'] ?? null,
            'runtime' => isset($data['runtime']) ? (int)$data['runtime'] : null,
            'tagline' => $data['tagline'] ?? null,
            'director' => $data['director'] ?? null,
            'cast' => isset($data['cast']) ? json_encode($data['cast']) : null,
            // Personal fields
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? $data['review'] ?? null,
            'status' => $data['status'] ?? 'watched',
            'release_date' => $this->formatDate($data['release_date'] ?? null),
            'completed_date' => $this->formatDate($data['completed_date'] ?? null)
        ];
        
        $id = UserMovie::create($movieData);
        ApiResponse::success(['id' => $id], 201);
    }
    
    /**
     * Get movie by ID
     */
    public function getMovie(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $movie = UserMovie::getById($id);
        
        if (!$movie) {
            throw new NotFoundException('Movie not found');
        }
        
        ApiResponse::success($movie);
    }
    
    /**
     * Update movie
     */
    public function updateMovie(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $movie = UserMovie::getById($id);
        
        if (!$movie) {
            throw new NotFoundException('Movie not found');
        }
        
        $data = is_array($req->body) ? $req->body : [];
        
        $updateData = [];
        $allowedFields = [
            'title', 'original_title', 'cover_image_cdn', 'cover_image_local',
            'overview', 'genres', 'external_rating', 'backdrop_image_cdn', 'backdrop_image_local',
            'runtime', 'tagline', 'director', 'cast',
            'my_rating', 'my_review', 'status', 'completed_date'
        ];
        $jsonFields = ['genres', 'cast'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, $jsonFields) && is_array($data[$field])) {
                    $updateData[$field] = json_encode($data[$field]);
                } elseif ($field === 'title_zh') {
                    $val = trim((string)$data[$field]);
                    $updateData[$field] = $val === '' ? null : $val;
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (!empty($updateData)) {
            UserMovie::update($id, $updateData);
        }
        
        ApiResponse::success(['message' => 'Movie updated']);
    }
    
    /**
     * Delete movie
     */
    public function deleteMovie(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $movie = UserMovie::getById($id);
        
        if (!$movie) {
            throw new NotFoundException('Movie not found');
        }
        
        UserMovie::delete($id);
        ApiResponse::success();
    }
    
    // TV Shows
    public function listTvShows(Request $req): void {
        $userId = $this->getUserId($req);
        $status = $req->query['status'] ?? null;
        $limit = $this->getLimit($req, 20);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $search = $req->query['search'] ?? null;
        $sort = $req->query['sort'] ?? 'date_desc';
        
        $items = UserTvShow::list($userId, $status, $limit, $offset, $search, $sort);
        $total = UserTvShow::count($userId, $status, $search);
        
        ApiResponse::success([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ]);
    }
    
    public function addTvShow(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        
        $errs = Validator::required($data, ['tmdb_id']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);
        
        $userId = $this->getUserId($req);
        
        if (UserTvShow::exists($userId, (int)$data['tmdb_id'])) {
            throw new ValidationException('TV show already in library');
        }
        
        $tvData = [
            'user_id' => $userId,
            'tmdb_id' => (int)$data['tmdb_id'],
            // Metadata fields
            'title' => $data['title'] ?? null,
            'original_title' => $data['original_title'] ?? null,
            'cover_image_cdn' => $data['cover_image_cdn'] ?? null,
            'overview' => $data['overview'] ?? null,
            'genres' => isset($data['genres']) ? json_encode($data['genres']) : null,
            'external_rating' => isset($data['external_rating']) ? (float)$data['external_rating'] : null,
            'backdrop_image_cdn' => $data['backdrop_image_cdn'] ?? null,
            'number_of_seasons' => isset($data['number_of_seasons']) ? (int)$data['number_of_seasons'] : null,
            'number_of_episodes' => isset($data['number_of_episodes']) ? (int)$data['number_of_episodes'] : null,
            'episode_runtime' => isset($data['episode_runtime']) ? (int)$data['episode_runtime'] : null,
            'networks' => isset($data['networks']) ? json_encode($data['networks']) : null,
            // Personal fields
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? $data['review'] ?? null,
            'current_season' => isset($data['current_season']) ? (int)$data['current_season'] : null,
            'current_episode' => isset($data['current_episode']) ? (int)$data['current_episode'] : null,
            'status' => $data['status'] ?? 'watching',
            'first_air_date' => $this->formatDate($data['first_air_date'] ?? null),
            'completed_date' => $this->formatDate($data['completed_date'] ?? null)
        ];
        
        $id = UserTvShow::create($tvData);
        ApiResponse::success(['id' => $id], 201);
    }
    
    public function getTvShow(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $tvShow = UserTvShow::getById($id);
        
        if (!$tvShow) {
            throw new NotFoundException('TV show not found');
        }
        
        ApiResponse::success($tvShow);
    }
    
    public function updateTvShow(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $tvShow = UserTvShow::getById($id);
        
        if (!$tvShow) {
            throw new NotFoundException('TV show not found');
        }
        
        $data = is_array($req->body) ? $req->body : [];
        
        $updateData = [];
        $allowedFields = [
            'title', 'original_title', 'cover_image_cdn', 'cover_image_local',
            'overview', 'genres', 'external_rating', 'backdrop_image_cdn', 'backdrop_image_local',
            'number_of_seasons', 'number_of_episodes', 'episode_runtime', 'networks',
            'my_rating', 'my_review', 'current_season', 'current_episode', 'status', 'completed_date'
        ];
        $jsonFields = ['genres', 'networks'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, $jsonFields) && is_array($data[$field])) {
                    $updateData[$field] = json_encode($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (!empty($updateData)) {
            UserTvShow::update($id, $updateData);
        }
        
        ApiResponse::success(['message' => 'TV show updated']);
    }
    
    public function updateTvShowProgress(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $tvShow = UserTvShow::getById($id);
        
        if (!$tvShow) {
            throw new NotFoundException('TV show not found');
        }
        
        $data = is_array($req->body) ? $req->body : [];
        $errs = Validator::required($data, ['current_season', 'current_episode']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);
        
        UserTvShow::updateProgress($id, (int)$data['current_season'], (int)$data['current_episode']);
        ApiResponse::success(['message' => 'Progress updated']);
    }
    
    public function deleteTvShow(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $tvShow = UserTvShow::getById($id);
        
        if (!$tvShow) {
            throw new NotFoundException('TV show not found');
        }
        
        UserTvShow::delete($id);
        ApiResponse::success();
    }
    
    // Books - Similar pattern (I'll create concise versions)
    public function listBooks(Request $req): void {
        $userId = $this->getUserId($req);
        $status = $req->query['status'] ?? null;
        $limit = $this->getLimit($req, 20);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $search = $req->query['search'] ?? null;
        $sort = $req->query['sort'] ?? 'date_desc';
        
        $items = UserBook::list($userId, $status, $limit, $offset, $search, $sort);
        $total = UserBook::count($userId, $status, $search);
        
        ApiResponse::success([
            'items' => $items,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]
        ]);
    }
    
    public function addBook(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        $userId = $this->getUserId($req);
        
        $bookData = [
            'user_id' => $userId,
            'google_books_id' => $data['google_books_id'] ?? null,
            'isbn' => $data['isbn'] ?? null,
            // Metadata fields
            'title' => $data['title'] ?? null,
            'original_title' => $data['original_title'] ?? null,
            'cover_image_cdn' => $data['cover_image_cdn'] ?? null,
            'overview' => $data['overview'] ?? null,
            'genres' => isset($data['genres']) ? json_encode($data['genres']) : null,
            'external_rating' => isset($data['external_rating']) ? (float)$data['external_rating'] : null,
            'authors' => isset($data['authors']) ? json_encode($data['authors']) : null,
            'publisher' => $data['publisher'] ?? null,
            'published_date' => $this->formatDate($data['published_date'] ?? null),
            'page_count' => isset($data['page_count']) ? (int)$data['page_count'] : null,
            'isbn_10' => $data['isbn_10'] ?? null,
            'isbn_13' => $data['isbn_13'] ?? null,
            'language' => $data['language'] ?? null,
            // Personal fields
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? null,
            'status' => $data['status'] ?? 'read',
            'publication_date' => $this->formatDate($data['publication_date'] ?? null),
            'completed_date' => $this->formatDate($data['completed_date'] ?? null)
        ];
        
        $id = UserBook::create($bookData);
        ApiResponse::success(['id' => $id], 201);
    }
    
    public function getBook(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $book = UserBook::getById($id);
        if (!$book) throw new NotFoundException('Book not found');
        ApiResponse::success($book);
    }
    
    public function updateBook(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserBook::getById($id)) throw new NotFoundException('Book not found');
        
        $data = is_array($req->body) ? $req->body : [];
        $updateData = [];
        $allowedFields = [
            'title', 'original_title', 'cover_image_cdn', 'cover_image_local',
            'overview', 'genres', 'external_rating',
            'authors', 'publisher', 'published_date', 'page_count', 'isbn_10', 'isbn_13', 'language',
            'my_rating', 'my_review', 'status', 'completed_date'
        ];
        $jsonFields = ['genres', 'authors'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, $jsonFields) && is_array($data[$field])) {
                    $updateData[$field] = json_encode($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (!empty($updateData)) UserBook::update($id, $updateData);
        ApiResponse::success(['message' => 'Book updated']);
    }
    
    public function deleteBook(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserBook::getById($id)) throw new NotFoundException('Book not found');
        UserBook::delete($id);
        ApiResponse::success();
    }
    
    // Games - Similar pattern
    public function listGames(Request $req): void {
        $userId = $this->getUserId($req);
        $status = $req->query['status'] ?? null;
        $limit = $this->getLimit($req, 20);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $search = $req->query['search'] ?? null;
        $sort = $req->query['sort'] ?? 'date_desc';
        
        $items = UserGame::list($userId, $status, $limit, $offset, $search, $sort);
        $total = UserGame::count($userId, $status, $search);
        
        ApiResponse::success([
            'items' => $items,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]
        ]);
    }
    
    public function addGame(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];

        // Manual add supported: title/name is required; igdb_id/rawg_id are optional.
        $title = isset($data['title']) ? trim((string)$data['title']) : '';
        $name = isset($data['name']) ? trim((string)$data['name']) : '';
        if ($title === '' && $name === '') {
            throw new ValidationException('Bad Request', ['title is required']);
        }

        $userId = $this->getUserId($req);

        $requestedSource = isset($data['source']) ? strtolower(trim((string)$data['source'])) : '';
        if (!in_array($requestedSource, ['manual', 'igdb', 'rawg'], true)) {
            $requestedSource = '';
        }

        // Optional external IDs (unless source explicitly says otherwise)
        $igdbId = isset($data['igdb_id']) && $data['igdb_id'] !== '' && $data['igdb_id'] !== null ? (int)$data['igdb_id'] : null;
        $rawgId = isset($data['rawg_id']) && $data['rawg_id'] !== '' && $data['rawg_id'] !== null ? (int)$data['rawg_id'] : null;

        // If explicitly manual, ignore any placeholder ids from clients.
        if ($requestedSource === 'manual') {
            $igdbId = null;
            $rawgId = null;
        } elseif ($requestedSource === 'igdb') {
            $rawgId = null;
            if (!$igdbId) {
                throw new ValidationException('Bad Request', ['igdb_id is required for source=igdb']);
            }
        } elseif ($requestedSource === 'rawg') {
            $igdbId = null;
            if (!$rawgId) {
                throw new ValidationException('Bad Request', ['rawg_id is required for source=rawg']);
            }
        }

        // Check for duplicates
        if ($rawgId && UserGame::exists($userId, $rawgId)) {
            throw new ValidationException('Game already in library');
        }
        if ($igdbId && UserGame::existsByIgdbId($userId, $igdbId)) {
            throw new ValidationException('Game already in library');
        }

        $source = $requestedSource !== '' ? $requestedSource : ($igdbId ? 'igdb' : ($rawgId ? 'rawg' : 'manual'));
        $sourceId = $igdbId ? $igdbId : ($rawgId ? $rawgId : null);
        
        $gameData = [
            'user_id' => $userId,
            'rawg_id' => $rawgId,
            'igdb_id' => $igdbId,
            'source' => $source,
            'source_id' => $sourceId,
            // Metadata fields
            'name' => $name !== '' ? $name : $title,
            'title_zh' => isset($data['title_zh']) ? (($v = trim((string)$data['title_zh'])) === '' ? null : $v) : null,
            'cover_image_cdn' => $data['cover_image_cdn'] ?? $data['cover_url'] ?? null,
            'overview' => $data['overview'] ?? null,
            'genres' => isset($data['genres']) ? json_encode($data['genres']) : null,
            'external_rating' => isset($data['external_rating']) ? (float)$data['external_rating'] : null,
            'backdrop_image_cdn' => $data['backdrop_image_cdn'] ?? null,
            'platforms' => isset($data['platforms']) ? json_encode($data['platforms']) : null,
            'developers' => isset($data['developers']) ? json_encode($data['developers']) : null,
            'publishers' => isset($data['publishers']) ? json_encode($data['publishers']) : null,
            'game_modes' => isset($data['game_modes']) ? json_encode($data['game_modes']) : null,
            // Personal fields
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? $data['review'] ?? null,
            'playtime_hours' => isset($data['playtime_hours']) ? (int)$data['playtime_hours'] : null,
            'platform' => $data['platform'] ?? null,
            'status' => $data['status'] ?? 'played',
            'release_date' => $this->formatDate($data['release_date'] ?? $data['released'] ?? null),
            'completed_date' => $this->formatDate($data['completed_date'] ?? $data['date'] ?? null)
        ];

        try {
            $id = UserGame::create($gameData);
            ApiResponse::success(['id' => $id], 201);
        } catch (\PDOException $e) {
            $msg = $e->getMessage();

            // Common production issue: DB schema not migrated yet (rawg_id still NOT NULL / missing new columns)
            if (stripos($msg, 'rawg_id') !== false && stripos($msg, 'cannot be null') !== false) {
                throw new InternalServerErrorException('Database schema outdated', [
                    'reason' => 'rawg_id_not_nullable',
                    'hint' => 'Apply migrations/017_fix_rawg_id_constraint.sql so user_games.rawg_id can be NULL',
                ]);
            }
            if (stripos($msg, 'unknown column') !== false && (stripos($msg, 'source') !== false || stripos($msg, 'source_id') !== false)) {
                throw new InternalServerErrorException('Database schema outdated', [
                    'reason' => 'missing_source_columns',
                    'hint' => 'Apply migrations/029_add_source_fields_to_user_games.sql to add user_games.source/source_id',
                ]);
            }

            throw new InternalServerErrorException('Internal Server Error', [
                'reason' => 'db_error',
                'hint' => 'Check server logs for PDOException and ensure migrations are applied',
            ]);
        }
    }
    
    public function getGame(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $game = UserGame::getById($id);
        if (!$game) throw new NotFoundException('Game not found');
        ApiResponse::success($game);
    }
    
    public function updateGame(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserGame::getById($id)) throw new NotFoundException('Game not found');
        
        $data = is_array($req->body) ? $req->body : [];
        $updateData = [];
        $allowedFields = [
            'name', 'title_zh', 'cover_image_cdn', 'cover_image_local',
            'overview', 'genres', 'external_rating', 'backdrop_image_cdn', 'backdrop_image_local',
            'platforms', 'developers', 'publishers', 'game_modes',
            'igdb_id', 'my_rating', 'my_review', 'playtime_hours', 'platform', 'status', 'completed_date'
        ];
        $jsonFields = ['genres', 'platforms', 'developers', 'publishers', 'game_modes'];
        $fieldAliases = ['cover_url' => 'cover_image_cdn', 'title' => 'name', 'review' => 'my_review', 'date' => 'completed_date'];
        
        // Handle aliases
        foreach ($fieldAliases as $alias => $target) {
            if (isset($data[$alias]) && !isset($data[$target])) {
                $data[$target] = $data[$alias];
            }
        }
        
        foreach ($allowedFields as $field) {
            if ($field === 'title_zh' && array_key_exists('title_zh', $data)) {
                $val = $data['title_zh'];
                if ($val === null) {
                    $updateData['title_zh'] = null;
                } else {
                    $trimmed = trim((string)$val);
                    $updateData['title_zh'] = $trimmed === '' ? null : $trimmed;
                }
                continue;
            }

            if (isset($data[$field])) {
                if (in_array($field, $jsonFields) && is_array($data[$field])) {
                    $updateData[$field] = json_encode($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (!empty($updateData)) UserGame::update($id, $updateData);
        ApiResponse::success(['message' => 'Game updated']);
    }
    
    public function deleteGame(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserGame::getById($id)) throw new NotFoundException('Game not found');
        UserGame::delete($id);
        ApiResponse::success();
    }
    
    // ============================================
    // Podcasts
    // ============================================
    public function listPodcasts(Request $req): void {
        $userId = $this->getUserId($req);
        $status = $req->query['status'] ?? null;
        $limit = $this->getLimit($req, 20);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $search = $req->query['search'] ?? null;
        $sort = $req->query['sort'] ?? 'date_desc';
        
        $items = UserPodcast::list($userId, $status, $limit, $offset, $search, $sort);
        $total = UserPodcast::count($userId, $status, $search);
        
        ApiResponse::success([
            'items' => $items,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]
        ]);
    }
    
    public function addPodcast(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        $userId = $this->getUserId($req);
        
        // Check for duplicates
        $itunesId = isset($data['itunes_id']) ? (int)$data['itunes_id'] : null;
        $podcastId = $data['podcast_id'] ?? null;
        
        if ($itunesId && UserPodcast::existsByItunesId($userId, $itunesId)) {
            throw new ValidationException('Podcast already in library');
        }
        if ($podcastId && UserPodcast::exists($userId, $podcastId)) {
            throw new ValidationException('Podcast already in library');
        }
        
        $podcastData = [
            'user_id' => $userId,
            'podcast_id' => $podcastId,
            'itunes_id' => $itunesId,
            // Metadata fields
            'title' => $data['title'] ?? null,
            'cover_image_cdn' => $data['cover_image_cdn'] ?? $data['artwork_url'] ?? null,
            'overview' => $data['overview'] ?? null,
            'genres' => isset($data['genres']) ? json_encode($data['genres']) : null,
            'external_rating' => isset($data['external_rating']) ? (float)$data['external_rating'] : null,
            'artist_name' => $data['artist_name'] ?? $data['host'] ?? null,
            'feed_url' => $data['feed_url'] ?? $data['rss_feed'] ?? null,
            'episode_count' => isset($data['episode_count']) ? (int)$data['episode_count'] : null,
            'explicit' => isset($data['explicit']) ? (int)(bool)$data['explicit'] : 0,
            // Personal fields
            'host' => $data['host'] ?? null,
            'rss_feed' => $data['rss_feed'] ?? null,
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? $data['review'] ?? null,
            'episodes_listened' => isset($data['episodes_listened']) ? (int)$data['episodes_listened'] : 0,
            'total_episodes' => isset($data['total_episodes']) && $data['total_episodes'] ? (int)$data['total_episodes'] : null,
            'status' => $data['status'] ?? 'listening',
            'first_release_date' => $this->formatDate($data['first_release_date'] ?? null),
            'release_date' => $this->formatDate($data['release_date'] ?? null),
            'completed_date' => $this->formatDate($data['completed_date'] ?? $data['date'] ?? null)
        ];
        
        $id = UserPodcast::create($podcastData);
        ApiResponse::success(['id' => $id], 201);
    }
    
    public function getPodcast(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $podcast = UserPodcast::getById($id);
        if (!$podcast) throw new NotFoundException('Podcast not found');
        ApiResponse::success($podcast);
    }
    
    public function updatePodcast(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserPodcast::getById($id)) throw new NotFoundException('Podcast not found');
        
        $data = is_array($req->body) ? $req->body : [];
        $updateData = [];
        $allowedFields = [
            'title', 'cover_image_cdn', 'cover_image_local',
            'overview', 'genres', 'external_rating',
            'artist_name', 'feed_url', 'episode_count', 'explicit',
            'host', 'my_rating', 'my_review', 'episodes_listened', 'status', 'completed_date'
        ];
        $jsonFields = ['genres'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, $jsonFields) && is_array($data[$field])) {
                    $updateData[$field] = json_encode($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (!empty($updateData)) UserPodcast::update($id, $updateData);
        ApiResponse::success(['message' => 'Podcast updated']);
    }
    
    public function deletePodcast(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserPodcast::getById($id)) throw new NotFoundException('Podcast not found');
        UserPodcast::delete($id);
        ApiResponse::success();
    }
    
    // ============================================
    // Documentaries
    // ============================================
    public function listDocumentaries(Request $req): void {
        $userId = $this->getUserId($req);
        $status = $req->query['status'] ?? null;
        $limit = $this->getLimit($req, 20);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $search = $req->query['search'] ?? null;
        $sort = $req->query['sort'] ?? 'date_desc';
        
        $items = UserDocumentary::list($userId, $status, $limit, $offset, $search, $sort);
        $total = UserDocumentary::count($userId, $status, $search);
        
        ApiResponse::success([
            'items' => $items,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]
        ]);
    }
    
    public function addDocumentary(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        $errs = Validator::required($data, ['tmdb_id']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);
        
        $userId = $this->getUserId($req);
        if (UserDocumentary::exists($userId, (int)$data['tmdb_id'])) {
            throw new ValidationException('Documentary already in library');
        }
        
        $docData = [
            'user_id' => $userId,
            'tmdb_id' => (int)$data['tmdb_id'],
            // Metadata fields
            'title' => $data['title'] ?? null,
            'original_title' => $data['original_title'] ?? null,
            'cover_image_cdn' => $data['cover_image_cdn'] ?? null,
            'overview' => $data['overview'] ?? null,
            'genres' => isset($data['genres']) ? json_encode($data['genres']) : null,
            'external_rating' => isset($data['external_rating']) ? (float)$data['external_rating'] : null,
            'backdrop_image_cdn' => $data['backdrop_image_cdn'] ?? null,
            'number_of_seasons' => isset($data['number_of_seasons']) ? (int)$data['number_of_seasons'] : null,
            'number_of_episodes' => isset($data['number_of_episodes']) ? (int)$data['number_of_episodes'] : null,
            'episode_runtime' => isset($data['episode_runtime']) ? (int)$data['episode_runtime'] : null,
            'networks' => isset($data['networks']) ? json_encode($data['networks']) : null,
            // Personal fields
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? $data['review'] ?? null,
            'status' => $data['status'] ?? 'watched',
            'release_date' => $this->formatDate($data['release_date'] ?? null),
            'completed_date' => $this->formatDate($data['completed_date'] ?? null)
        ];
        
        $id = UserDocumentary::create($docData);
        ApiResponse::success(['id' => $id], 201);
    }
    
    public function getDocumentary(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $doc = UserDocumentary::getById($id);
        if (!$doc) throw new NotFoundException('Documentary not found');
        ApiResponse::success($doc);
    }
    
    public function updateDocumentary(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserDocumentary::getById($id)) throw new NotFoundException('Documentary not found');
        
        $data = is_array($req->body) ? $req->body : [];
        $updateData = [];
        $allowedFields = [
            'title', 'original_title', 'cover_image_cdn', 'cover_image_local',
            'overview', 'genres', 'external_rating', 'backdrop_image_cdn', 'backdrop_image_local',
            'number_of_seasons', 'number_of_episodes', 'episode_runtime', 'networks',
            'my_rating', 'my_review', 'status', 'completed_date'
        ];
        $jsonFields = ['genres', 'networks'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, $jsonFields) && is_array($data[$field])) {
                    $updateData[$field] = json_encode($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (!empty($updateData)) UserDocumentary::update($id, $updateData);
        ApiResponse::success(['message' => 'Documentary updated']);
    }
    
    public function deleteDocumentary(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserDocumentary::getById($id)) throw new NotFoundException('Documentary not found');
        UserDocumentary::delete($id);
        ApiResponse::success();
    }
    
    // ============================================
    // Anime
    // ============================================
    public function listAnime(Request $req): void {
        $userId = $this->getUserId($req);
        $status = $req->query['status'] ?? null;
        $limit = $this->getLimit($req, 20);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $search = $req->query['search'] ?? null;
        $sort = $req->query['sort'] ?? 'date_desc';
        
        $items = UserAnime::list($userId, $status, $limit, $offset, $search, $sort);
        $total = UserAnime::count($userId, $status, $search);
        
        ApiResponse::success([
            'items' => $items,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]
        ]);
    }
    
    public function addAnime(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        
        // Accept either anilist_id or anime_id
        if (empty($data['anilist_id']) && empty($data['anime_id'])) {
            throw new ValidationException('Bad Request', ['anilist_id or anime_id is required']);
        }
        
        $userId = $this->getUserId($req);
        $anilistId = isset($data['anilist_id']) ? (int)$data['anilist_id'] : null;
        $animeId = isset($data['anime_id']) ? (int)$data['anime_id'] : null;
        
        // Check for duplicates
        if ($anilistId && UserAnime::existsByAnilistId($userId, $anilistId)) {
            throw new ValidationException('Anime already in library');
        }
        if ($animeId && UserAnime::exists($userId, $animeId)) {
            throw new ValidationException('Anime already in library');
        }
        
        $animeData = [
            'user_id' => $userId,
            'anime_id' => $animeId,
            'anilist_id' => $anilistId,
            // Metadata fields
            'title' => $data['title'] ?? null,
            'original_title' => $data['original_title'] ?? null,
            'cover_image_cdn' => $data['cover_image_cdn'] ?? $data['cover_url'] ?? null,
            'overview' => $data['overview'] ?? null,
            'genres' => isset($data['genres']) ? json_encode($data['genres']) : null,
            'external_rating' => isset($data['external_rating']) ? (float)$data['external_rating'] : null,
            'backdrop_image_cdn' => $data['backdrop_image_cdn'] ?? null,
            'format' => $data['format'] ?? null,
            'season_info' => $data['season_info'] ?? $data['season'] ?? null,
            'studio' => $data['studio'] ?? null,
            'source' => $data['source'] ?? null,
            // Personal fields
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? $data['review'] ?? null,
            'episodes_watched' => isset($data['episodes_watched']) ? (int)$data['episodes_watched'] : 0,
            'total_episodes' => isset($data['total_episodes']) && $data['total_episodes'] ? (int)$data['total_episodes'] : null,
            'status' => $data['status'] ?? 'watching',
            'first_air_date' => $this->formatDate($data['first_air_date'] ?? null),
            'release_date' => $this->formatDate($data['release_date'] ?? null),
            'completed_date' => $this->formatDate($data['completed_date'] ?? $data['date'] ?? null)
        ];
        
        $id = UserAnime::create($animeData);
        ApiResponse::success(['id' => $id], 201);
    }
    
    public function getAnime(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        $anime = UserAnime::getById($id);
        if (!$anime) throw new NotFoundException('Anime not found');
        ApiResponse::success($anime);
    }
    
    public function updateAnime(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserAnime::getById($id)) throw new NotFoundException('Anime not found');
        
        $data = is_array($req->body) ? $req->body : [];
        $updateData = [];
        $allowedFields = [
            'title', 'original_title', 'cover_image_cdn', 'cover_image_local',
            'overview', 'genres', 'external_rating', 'backdrop_image_cdn', 'backdrop_image_local',
            'format', 'season_info', 'studio', 'source',
            'my_rating', 'my_review', 'episodes_watched', 'total_episodes', 'status', 'completed_date'
        ];
        $jsonFields = ['genres'];
        $fieldAliases = ['cover_url' => 'cover_image_cdn', 'season' => 'season_info', 'review' => 'my_review'];
        
        // Handle aliases
        foreach ($fieldAliases as $alias => $target) {
            if (isset($data[$alias]) && !isset($data[$target])) {
                $data[$target] = $data[$alias];
            }
        }
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, $jsonFields) && is_array($data[$field])) {
                    $updateData[$field] = json_encode($data[$field]);
                } else {
                    $updateData[$field] = $data[$field];
                }
            }
        }
        
        if (!empty($updateData)) UserAnime::update($id, $updateData);
        ApiResponse::success(['message' => 'Anime updated']);
    }
    
    public function updateAnimeProgress(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserAnime::getById($id)) throw new NotFoundException('Anime not found');
        
        $data = is_array($req->body) ? $req->body : [];
        $errs = Validator::required($data, ['episodes_watched']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);
        
        UserAnime::updateProgress($id, (int)$data['episodes_watched']);
        ApiResponse::success(['message' => 'Progress updated']);
    }
    
    public function deleteAnime(Request $req, array $params): void {
        $id = (int)($params['id'] ?? 0);
        if (!UserAnime::getById($id)) throw new NotFoundException('Anime not found');
        UserAnime::delete($id);
        ApiResponse::success();
    }
}
?>
