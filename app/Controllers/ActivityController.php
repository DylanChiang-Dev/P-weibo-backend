<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\ApiResponse;
use App\Core\Validator;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Models\DailyActivity;

class ActivityController {
    /**
     * Check in or update daily activity
     */
    public function checkin(Request $req): void {
        $data = is_array($req->body) ? $req->body : [];
        
        // Validation
        $errs = Validator::required($data, ['activity_type', 'activity_date']);
        if (!empty($errs)) throw new ValidationException('Bad Request', $errs);
        
        // Validate activity_type
        $validTypes = ['exercise', 'reading', 'duolingo'];
        if (!in_array($data['activity_type'], $validTypes)) {
            throw new ValidationException('Invalid activity_type', ['activity_type' => 'Must be one of: exercise, reading, duolingo']);
        }
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['activity_date'])) {
            throw new ValidationException('Invalid date format', ['activity_date' => 'Must be YYYY-MM-DD']);
        }
        
        // Validate intensity if provided
        if (isset($data['intensity']) && !in_array($data['intensity'], ['low', 'medium', 'high'])) {
            throw new ValidationException('Invalid intensity', ['intensity' => 'Must be one of: low, medium, high']);
        }
        
        $userId = (int)$req->user['id'];
        
        $activityData = [
            'user_id' => $userId,
            'activity_type' => $data['activity_type'],
            'activity_date' => $data['activity_date'],
            'duration_minutes' => isset($data['duration_minutes']) ? (int)$data['duration_minutes'] : null,
            'pages_read' => isset($data['pages_read']) ? (int)$data['pages_read'] : null,
            'xp_earned' => isset($data['xp_earned']) ? (int)$data['xp_earned'] : null,
            'courses_completed' => isset($data['courses_completed']) ? (int)$data['courses_completed'] : null,
            'notes' => $data['notes'] ?? null,
            'intensity' => $data['intensity'] ?? null
        ];
        
        $id = DailyActivity::checkin($activityData);
        
        ApiResponse::success(['id' => $id, 'message' => 'Activity recorded successfully'], 201);
    }
    
    /**
     * Get heatmap data for visualization
     */
    public function heatmap(Request $req): void {
        $userId = (int)$req->user['id'];
        $type = $req->query['type'] ?? '';
        $year = isset($req->query['year']) ? (int)$req->query['year'] : (int)date('Y');
        
        // Validate type
        $validTypes = ['exercise', 'reading', 'duolingo'];
        if (!in_array($type, $validTypes)) {
            throw new ValidationException('Invalid type', ['type' => 'Must be one of: exercise, reading, duolingo']);
        }
        
        $data = DailyActivity::getHeatmapData($userId, $type, $year);
        
        ApiResponse::success($data);
    }
    
    /**
     * Get statistics
     */
    public function stats(Request $req): void {
        $userId = (int)$req->user['id'];
        $type = $req->query['type'] ?? '';
        $year = isset($req->query['year']) ? (int)$req->query['year'] : (int)date('Y');
        
        // Validate type
        $validTypes = ['exercise', 'reading', 'duolingo'];
        if (!in_array($type, $validTypes)) {
            throw new ValidationException('Invalid type', ['type' => 'Must be one of: exercise, reading, duolingo']);
        }
        
        $stats = DailyActivity::getStats($userId, $type, $year);
        
        ApiResponse::success($stats);
    }
    
    /**
     * Get all activities for a specific date
     */
    public function daily(Request $req): void {
        $userId = (int)$req->user['id'];
        $date = $req->query['date'] ?? date('Y-m-d');
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new ValidationException('Invalid date format', ['date' => 'Must be YYYY-MM-DD']);
        }
        
        $activities = DailyActivity::getByDate($userId, $date);
        
        ApiResponse::success($activities);
    }
}
?>
