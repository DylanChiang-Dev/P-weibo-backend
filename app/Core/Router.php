<?php
namespace App\Core;

use App\Core\Auth;

class Router {
    private Request $request;
    private array $routes = [];

    public function __construct(Request $request) { $this->request = $request; }

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

    private function add(string $method, string $path, callable|array $handler, array $options): void {
        $this->routes[$method][] = [$path, $handler, $options];
    }

    public function dispatch(): void {
        $method = $this->request->method;
        $path = rtrim($this->request->path, '/') ?: '/';
        $candidates = $this->routes[$method] ?? [];
        foreach ($candidates as [$pattern, $handler, $opts]) {
            $regex = $this->compilePattern($pattern);
            if (preg_match($regex, $path, $m)) {
                $params = $this->extractParams($regex, $m);
                if (($opts['auth'] ?? false) === true) {
                    $user = Auth::requireAccess($this->request->bearerToken());
                    $this->request->user = $user;
                }
                $this->invoke($handler, $params);
                return;
            }
        }
        Response::json(['success' => false, 'error' => 'Not Found'], 404);
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