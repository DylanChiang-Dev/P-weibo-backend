<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Core\Validator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
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
        $limit = min((int)($req->query['limit'] ?? 20), 100);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        
        $items = UserMovie::list($userId, $status, $limit, $offset);
        $total = UserMovie::count($userId, $status);
        
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
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? null,
            'status' => $data['status'] ?? 'watched',
            'release_date' => $data['release_date'] ?? null,
            'completed_date' => $data['completed_date'] ?? null
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
        $allowedFields = ['my_rating', 'my_review', 'status', 'completed_date'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
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
        $limit = min((int)($req->query['limit'] ?? 20), 100);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        
        $items = UserTvShow::list($userId, $status, $limit, $offset);
        $total = UserTvShow::count($userId, $status);
        
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
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? null,
            'current_season' => isset($data['current_season']) ? (int)$data['current_season'] : null,
            'current_episode' => isset($data['current_episode']) ? (int)$data['current_episode'] : null,
            'status' => $data['status'] ?? 'watching',
            'first_air_date' => $data['first_air_date'] ?? null,
            'completed_date' => $data['completed_date'] ?? null
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
        $allowedFields = ['my_rating', 'my_review', 'current_season', 'current_episode', 'status', 'completed_date'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
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
        $limit = min((int)($req->query['limit'] ?? 20), 100);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        
        $items = UserBook::list($userId, $status, $limit, $offset);
        $total = UserBook::count($userId, $status);
        
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
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? null,
            'status' => $data['status'] ?? 'read',
            'publication_date' => $data['publication_date'] ?? null,
            'completed_date' => $data['completed_date'] ?? null
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
        foreach (['my_rating', 'my_review', 'status', 'completed_date'] as $field) {
            if (isset($data[$field])) $updateData[$field] = $data[$field];
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
        $limit = min((int)($req->query['limit'] ?? 20), 100);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        
        $items = UserGame::list($userId, $status, $limit, $offset);
        $total = UserGame::count($userId, $status);
        
        ApiResponse::success([
            'items' => $items,
            'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]
        ]);
    }
    
    public function addGame(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        
        // Accept either igdb_id or rawg_id (at least one required)
        if (empty($data['igdb_id']) && empty($data['rawg_id'])) {
            throw new ValidationException('Bad Request', ['igdb_id or rawg_id is required']);
        }
        
        $userId = $this->getUserId($req);
        
        // Check for duplicates by either ID
        $igdbId = isset($data['igdb_id']) ? (int)$data['igdb_id'] : null;
        $rawgId = isset($data['rawg_id']) && $data['rawg_id'] ? (int)$data['rawg_id'] : null;
        
        // Check for duplicates
        if ($rawgId && UserGame::exists($userId, $rawgId)) {
            throw new ValidationException('Game already in library');
        }
        if ($igdbId && UserGame::existsByIgdbId($userId, $igdbId)) {
            throw new ValidationException('Game already in library');
        }
        
        $gameData = [
            'user_id' => $userId,
            'rawg_id' => $rawgId,
            'igdb_id' => $igdbId,
            'name' => $data['name'] ?? null,
            'cover_url' => $data['cover_url'] ?? null,
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? $data['review'] ?? null,
            'playtime_hours' => isset($data['playtime_hours']) ? (int)$data['playtime_hours'] : null,
            'platform' => $data['platform'] ?? null,
            'status' => $data['status'] ?? 'played',
            'release_date' => $this->formatDate($data['release_date'] ?? $data['released'] ?? null),
            'completed_date' => $this->formatDate($data['completed_date'] ?? $data['date'] ?? null)
        ];
        
        $id = UserGame::create($gameData);
        ApiResponse::success(['id' => $id], 201);
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
        
        // Map frontend field names to database fields
        $fieldMappings = [
            'igdb_id' => 'igdb_id',
            'name' => 'name',
            'cover_url' => 'cover_url',
            'my_rating' => 'my_rating',
            'my_review' => 'my_review',
            'review' => 'my_review',           // Frontend alias
            'playtime_hours' => 'playtime_hours',
            'platform' => 'platform',
            'status' => 'status',
            'completed_date' => 'completed_date',
            'date' => 'completed_date'          // Frontend alias
        ];
        
        foreach ($fieldMappings as $inputField => $dbField) {
            if (isset($data[$inputField])) {
                $updateData[$dbField] = $data[$inputField];
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
        $limit = min((int)($req->query['limit'] ?? 20), 100);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        
        $items = UserPodcast::list($userId, $status, $limit, $offset);
        $total = UserPodcast::count($userId, $status);
        
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
            'title' => $data['title'] ?? null,
            'artwork_url' => $data['artwork_url'] ?? null,
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
        foreach (['my_rating', 'my_review', 'episodes_listened', 'status', 'completed_date'] as $field) {
            if (isset($data[$field])) $updateData[$field] = $data[$field];
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
        $limit = min((int)($req->query['limit'] ?? 20), 100);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        
        $items = UserDocumentary::list($userId, $status, $limit, $offset);
        $total = UserDocumentary::count($userId, $status);
        
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
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? null,
            'status' => $data['status'] ?? 'watched',
            'release_date' => $data['release_date'] ?? null,
            'completed_date' => $data['completed_date'] ?? null
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
        foreach (['my_rating', 'my_review', 'status', 'completed_date'] as $field) {
            if (isset($data[$field])) $updateData[$field] = $data[$field];
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
        $limit = min((int)($req->query['limit'] ?? 20), 100);
        $page = max(1, (int)($req->query['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        
        $items = UserAnime::list($userId, $status, $limit, $offset);
        $total = UserAnime::count($userId, $status);
        
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
            'title' => $data['title'] ?? null,
            'cover_url' => $data['cover_url'] ?? null,
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
        foreach (['my_rating', 'my_review', 'episodes_watched', 'status', 'completed_date'] as $field) {
            if (isset($data[$field])) $updateData[$field] = $data[$field];
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
