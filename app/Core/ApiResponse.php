<?php
namespace App\Core;

/**
 * Standardized API response helper
 * Provides consistent response format for all API endpoints
 */
class ApiResponse {
    /**
     * Send a success response
     * 
     * @param mixed $data Response data
     * @param int $code HTTP status code (default 200)
     */
    public static function success($data = null, int $code = 200): void {
        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        Response::json($response, $code);
    }

    /**
     * Send an error response
     * 
     * @param string $message Error message
     * @param int $code HTTP status code (default 400)
     * @param mixed $errors Additional error details (e.g., validation errors)
     */
    public static function error(string $message, int $code = 400, $errors = null): void {
        $response = [
            'success' => false,
            'error' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        Response::json($response, $code);
    }

    /**
     * Send a paginated response
     * 
     * @param array $items Items for current page
     * @param array $meta Pagination metadata (cursor, hasMore, etc.)
     */
    public static function paginated(array $items, array $meta): void {
        self::success([
            'items' => $items,
            'meta' => $meta,
        ]);
    }
}
?>
