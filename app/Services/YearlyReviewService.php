<?php
namespace App\Services;

use App\Core\QueryBuilder;
use App\Models\DailyActivity;

class YearlyReviewService {
    
    /**
     * Get aggregated data for yearly review
     */
    public function getYearlyData(int $userId, int $year): array {
        return [
            'year' => $year,
            'habits' => $this->getHabitStats($userId, $year),
            'media' => [
                'summary' => $this->getMediaCounts($userId, $year),
                'top_items' => $this->getTopMediaItems($userId, $year)
            ]
        ];
    }
    
    /**
     * Get habit stats for all types
     */
    private function getHabitStats(int $userId, int $year): array {
        return [
            'exercise' => DailyActivity::getStats($userId, 'exercise', $year),
            'reading' => DailyActivity::getStats($userId, 'reading', $year),
            'duolingo' => DailyActivity::getStats($userId, 'duolingo', $year)
        ];
    }
    
    /**
     * Get counts for all media types
     */
    private function getMediaCounts(int $userId, int $year): array {
        $tables = [
            'user_movies' => 'movies_count',
            'user_books' => 'books_count',
            'user_games' => 'games_count',
            'user_anime' => 'anime_count',
            'user_tv_shows' => 'tv_shows_count',
            'user_documentaries' => 'documentaries_count',
            'user_podcasts' => 'podcasts_count'
        ];
        
        $counts = [];
        $startDate = "$year-01-01";
        $endDate = "$year-12-31";
        
        foreach ($tables as $table => $key) {
            $count = QueryBuilder::table($table)
                ->where('user_id', '=', $userId)
                ->whereRaw('completed_date >= ? AND completed_date <= ?', [$startDate, $endDate])
                ->count();
            
            $counts[$key] = $count;
        }
        
        return $counts;
    }
    
    /**
     * Get top items for each media type
     */
    private function getTopMediaItems(int $userId, int $year): array {
        $startDate = "$year-01-01";
        $endDate = "$year-12-31";
        $limit = 10;
        
        $mediaTypes = [
            'movies' => 'user_movies',
            'books' => 'user_books',
            'games' => 'user_games',
            'anime' => 'user_anime',
            'tv_shows' => 'user_tv_shows',
            'documentaries' => 'user_documentaries',
            'podcasts' => 'user_podcasts'
        ];
        
        $topItems = [];
        
        foreach ($mediaTypes as $key => $table) {
            $items = QueryBuilder::table($table)
                ->where('user_id', '=', $userId)
                ->whereRaw('completed_date >= ? AND completed_date <= ?', [$startDate, $endDate])
                ->orderBy('my_rating', 'DESC')
                ->orderBy('completed_date', 'DESC')
                ->limit($limit)
                ->get();
                
            $topItems[$key] = $items;
        }
        
        return $topItems;
    }
}
