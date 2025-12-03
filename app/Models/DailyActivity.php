<?php
namespace App\Models;

use App\Core\QueryBuilder;

class DailyActivity {
    /**
     * Check in or update activity for a specific date
     */
    public static function checkin(array $data): int {
        $userId = $data['user_id'];
        $activityType = $data['activity_type'];
        $activityDate = $data['activity_date'];
        
        // Check if exists
        $existing = QueryBuilder::table('daily_activities')
            ->where('user_id', '=', $userId)
            ->where('activity_type', '=', $activityType)
            ->where('activity_date', '=', $activityDate)
            ->first();
        
        if ($existing) {
            // Update existing
            self::update((int)$existing['id'], $data);
            return (int)$existing['id'];
        } else {
            // Create new
            return QueryBuilder::table('daily_activities')->insert($data);
        }
    }
    
    /**
     * Update activity record
     */
    public static function update(int $id, array $data): void {
        unset($data['user_id'], $data['activity_type'], $data['activity_date']); // immutable
        QueryBuilder::table('daily_activities')
            ->where('id', '=', $id)
            ->update($data);
    }
    
    /**
     * Get heatmap data for a specific type and year
     */
    public static function getHeatmapData(int $userId, string $type, int $year): array {
        $startDate = "$year-01-01";
        $endDate = "$year-12-31";
        
        $activities = QueryBuilder::table('daily_activities')
            ->where('user_id', '=', $userId)
            ->where('activity_type', '=', $type)
            ->whereRaw('activity_date >= ? AND activity_date <= ?', [$startDate, $endDate])
            ->orderBy('activity_date', 'ASC')
            ->get();
        
        // Fill in missing dates with zero values
        $result = [];
        $currentDate = new \DateTime($startDate);
        $lastDate = new \DateTime($endDate);
        $activityMap = [];
        
        foreach ($activities as $activity) {
            $activityMap[$activity['activity_date']] = $activity;
        }
        
        while ($currentDate <= $lastDate) {
            $dateStr = $currentDate->format('Y-m-d');
            if (isset($activityMap[$dateStr])) {
                $activity = $activityMap[$dateStr];
                $result[] = [
                    'date' => $dateStr,
                    'value' => (int)($activity['duration_minutes'] ?? $activity['pages_read'] ?? $activity['xp_earned'] ?? 0),
                    'intensity' => $activity['intensity'] ?? null,
                    'notes' => $activity['notes'] ?? null
                ];
            } else {
                $result[] = [
                    'date' => $dateStr,
                    'value' => 0
                ];
            }
            $currentDate->modify('+1 day');
        }
        
        return $result;
    }
    
    /**
     * Get statistics for a specific type and year
     */
    public static function getStats(int $userId, string $type, int $year): array {
        $startDate = "$year-01-01";
        $endDate = "$year-12-31";
        
        $activities = QueryBuilder::table('daily_activities')
            ->where('user_id', '=', $userId)
            ->where('activity_type', '=', $type)
            ->whereRaw('activity_date >= ? AND activity_date <= ?', [$startDate, $endDate])
            ->orderBy('activity_date', 'ASC')
            ->get();
        
        $totalDays = count($activities);
        $totalMinutes = 0;
        $totalPages = 0;
        $totalXP = 0;
        $monthlyBreakdown = array_fill(1, 12, ['days' => 0, 'minutes' => 0, 'pages' => 0, 'xp' => 0]);
        
        foreach ($activities as $activity) {
            $totalMinutes += (int)($activity['duration_minutes'] ?? 0);
            $totalPages += (int)($activity['pages_read'] ?? 0);
            $totalXP += (int)($activity['xp_earned'] ?? 0);
            
            $month = (int)date('n', strtotime($activity['activity_date']));
            $monthlyBreakdown[$month]['days']++;
            $monthlyBreakdown[$month]['minutes'] += (int)($activity['duration_minutes'] ?? 0);
            $monthlyBreakdown[$month]['pages'] += (int)($activity['pages_read'] ?? 0);
            $monthlyBreakdown[$month]['xp'] += (int)($activity['xp_earned'] ?? 0);
        }
        
        // Calculate streaks
        $streaks = self::calculateStreak($userId, $type);
        
        return [
            'total_days' => $totalDays,
            'total_minutes' => $totalMinutes,
            'total_pages' => $totalPages,
            'total_xp' => $totalXP,
            'current_streak' => $streaks['current'],
            'longest_streak' => $streaks['longest'],
            'monthly_breakdown' => array_values(array_map(function($month, $data) {
                return ['month' => $month] + $data;
            }, array_keys($monthlyBreakdown), $monthlyBreakdown))
        ];
    }
    
    /**
     * Get all activities for a specific date
     */
    public static function getByDate(int $userId, string $date): array {
        return QueryBuilder::table('daily_activities')
            ->where('user_id', '=', $userId)
            ->where('activity_date', '=', $date)
            ->get();
    }
    
    /**
     * Calculate current and longest streak
     */
    public static function calculateStreak(int $userId, string $type): array {
        $activities = QueryBuilder::table('daily_activities')
            ->select(['activity_date'])
            ->where('user_id', '=', $userId)
            ->where('activity_type', '=', $type)
            ->orderBy('activity_date', 'DESC')
            ->get();
        
        if (empty($activities)) {
            return ['current' => 0, 'longest' => 0];
        }
        
        $dates = array_column($activities, 'activity_date');
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Calculate current streak
        $currentStreak = 0;
        if ($dates[0] === $today || $dates[0] === $yesterday) {
            $currentStreak = 1;
            $prevDate = new \DateTime($dates[0]);
            
            for ($i = 1; $i < count($dates); $i++) {
                $currDate = new \DateTime($dates[$i]);
                $diff = $prevDate->diff($currDate)->days;
                
                if ($diff === 1) {
                    $currentStreak++;
                    $prevDate = $currDate;
                } else {
                    break;
                }
            }
        }
        
        // Calculate longest streak
        $longestStreak = 1;
        $tempStreak = 1;
        
        for ($i = 0; $i < count($dates) - 1; $i++) {
            $currDate = new \DateTime($dates[$i]);
            $nextDate = new \DateTime($dates[$i + 1]);
            $diff = $currDate->diff($nextDate)->days;
            
            if ($diff === 1) {
                $tempStreak++;
                $longestStreak = max($longestStreak, $tempStreak);
            } else {
                $tempStreak = 1;
            }
        }
        
        return ['current' => $currentStreak, 'longest' => $longestStreak];
    }
    
    /**
     * Delete activity
     */
    public static function delete(int $id): void {
        QueryBuilder::table('daily_activities')
            ->where('id', '=', $id)
            ->delete();
    }
}
?>
