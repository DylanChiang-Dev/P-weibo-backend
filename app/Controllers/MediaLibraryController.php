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

class MediaLibraryController {
    /**
     * List movies
     */
    public function listMovies(Request $req): void {
        $userId = (int)$req->user['id'];
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
        
        $userId = (int)$req->user['id'];
        
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
        $userId = (int)$req->user['id'];
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
        
        $userId = (int)$req->user['id'];
        
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
        $userId = (int)$req->user['id'];
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
        $userId = (int)$req->user['id'];
        
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
        $userId = (int)$req->user['id'];
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
        $errs = Validator::required($data, ['rawg_id']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);
        
        $userId = (int)$req->user['id'];
        if (UserGame::exists($userId, (int)$data['rawg_id'])) {
            throw new ValidationException('Game already in library');
        }
        
        $gameData = [
            'user_id' => $userId,
            'rawg_id' => (int)$data['rawg_id'],
            'my_rating' => isset($data['my_rating']) ? (float)$data['my_rating'] : null,
            'my_review' => $data['my_review'] ?? null,
            'playtime_hours' => isset($data['playtime_hours']) ? (int)$data['playtime_hours'] : null,
            'platform' => $data['platform'] ?? null,
            'status' => $data['status'] ?? 'played',
            'release_date' => $data['release_date'] ?? null,
            'completed_date' => $data['completed_date'] ?? null
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
        foreach (['my_rating', 'my_review', 'playtime_hours', 'platform', 'status', 'completed_date'] as $field) {
            if (isset($data[$field])) $updateData[$field] = $data[$field];
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
}
?>
