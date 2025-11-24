<?php
namespace App\Core;

use App\Exceptions\NotFoundException;
use Closure;

class Router {
    private Request $request;
    private array $routes = [];
    private array $globalMiddleware = [];

    public function __construct(Request $request) { 
        $this->request = $request; 
    }

    /**
     * Add global middleware to be executed on all routes
     */
    public function addGlobalMiddleware(Middleware $middleware): void {
        $this->globalMiddleware[] = $middleware;
    }

    public function get(string $path, callable|array $handler, array $options = []): void {
        $this->add('GET', $path, $handler, $options);
    }

    public function post(string $path, callable|array $handler, array $options = []): void {
        $this->add('POST', $path, $handler, $options);
    }

    public function delete(string $path, callable|array $handler, array $options = []): void {
        $this->add('DELETE', $path, $handler, $options);
    }

    public function put(string $path, callable|array $handler, array $options = []): void {
        $this->add('PUT', $path, $handler, $options);
    }

    public function patch(string $path, callable|array $handler, array $options = []): void {
        $this->add('PATCH', $path, $handler, $options);
    }

    private function add(string $method, string $path, callable|array $handler, array $options): void {
        $this->routes[$method][] = [$path, $handler, $options];
    }

    public function dispatch(): void {
        $method = $this->request->method;
        $path = rtrim($this->request->path, '/') ?: '/';
        
        // Handle OPTIONS preflight requests
        // Run global middleware (including CORS) then return 204
        if ($method === 'OPTIONS') {
            $this->runMiddlewarePipeline($this->globalMiddleware, function() {
                // OPTIONS handled by CorsMiddleware, no further action needed
            }, []);
            return;
        }
        
        $candidates = $this->routes[$method] ?? [];
        
        foreach ($candidates as [$pattern, $handler, $opts]) {
            $regex = $this->compilePattern($pattern);
            if (preg_match($regex, $path, $m)) {
                $params = $this->extractParams($regex, $m);
                
                // Build middleware stack
                $middleware = $this->globalMiddleware;
                
                // Add route-specific middleware (for backward compatibility with 'auth' option)
                if (($opts['auth'] ?? false) === true) {
                    $middleware[] = new \App\Middleware\AuthMiddleware();
                }
                
                // Add custom middleware if specified
                if (isset($opts['middleware'])) {
                    $customMiddleware = is_array($opts['middleware']) ? $opts['middleware'] : [$opts['middleware']];
                    foreach ($customMiddleware as $m) {
                        $middleware[] = is_string($m) ? new $m() : $m;
                    }
                }
                
                // Execute middleware pipeline
                $this->runMiddlewarePipeline($middleware, $handler, $params);
                return;
            }
        }
        
        throw new NotFoundException('Route not found');
    }

    /**
     * Execute middleware pipeline
     */
    private function runMiddlewarePipeline(array $middleware, callable|array $handler, array $params): void {
        $pipeline = array_reduce(
            array_reverse($middleware),
            function ($next, $middleware) {
                return function ($request) use ($next, $middleware) {
                    return $middleware->handle($request, $next);
                };
            },
            function ($request) use ($handler, $params) {
                return $this->invoke($handler, $params);
            }
        );

        $pipeline($this->request);
    }

    private function compilePattern(string $pattern): string {
        $pattern = rtrim($pattern, '/') ?: '/';
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }

    private function extractParams(string $regex, array $matches): array {
        $params = [];
        foreach ($matches as $k => $v) {
            if (!is_int($k)) $params[$k] = $v;
        }
        return $params;
    }

    private function invoke(callable|array $handler, array $params): void {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $obj = new $class();
            $obj->$method($this->request, $params);
        } else {
            $handler($this->request, $params);
        }
    }
}
?>