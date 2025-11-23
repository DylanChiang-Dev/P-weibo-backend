<?php
namespace App\Core;

use Closure;

/**
 * Middleware interface
 * All middleware must implement this interface
 */
interface Middleware {
    /**
     * Handle the request
     * 
     * @param Request $request The incoming request
     * @param Closure $next The next middleware in the pipeline
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed;
}
?>
