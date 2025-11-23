<?php
namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Services\RateLimitService;
use Closure;

/**
 * Rate limiting middleware
 * Prevents abuse by limiting request frequency
 */
class RateLimitMiddleware implements Middleware {
    private string $key;
    private int $maxAttempts;
    private int $decayMinutes;

    public function __construct(string $key = 'global', int $maxAttempts = 60, int $decayMinutes = 1) {
        $this->key = $key;
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    public function handle(Request $request, Closure $next): mixed {
        $identifier = $this->getIdentifier($request);
        
        if (!RateLimitService::attempt($identifier, $this->maxAttempts, $this->decayMinutes)) {
            Response::json([
                'success' => false,
                'error' => 'Too many requests. Please try again later.'
            ], 429);
        }

        return $next($request);
    }

    private function getIdentifier(Request $request): string {
        // Use IP address as identifier
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return "{$this->key}:{$ip}";
    }
}
?>
