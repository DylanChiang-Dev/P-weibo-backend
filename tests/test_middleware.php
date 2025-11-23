<?php
/**
 * Test Middleware System
 * Tests middleware interface and core middleware
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/config/config.php';
$config = config();

// Autoload
spl_autoload_register(function (string $class) use ($root) {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $path = $root . '/app/' . str_replace('\\', '/', $rel) . '.php';
    if (file_exists($path)) require_once $path;
});

use App\Core\Request;
use App\Core\Database;
use App\Core\Auth;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;

// Init
Database::init($config['db']);
Auth::init($config['jwt'], $config['app_url']);

echo "Running Middleware Tests...\n\n";

// Test 1: Middleware interface implementation
echo "Test 1: AuthMiddleware implements Middleware... ";
try {
    $middleware = new AuthMiddleware();
    if ($middleware instanceof \App\Core\Middleware) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 2: CorsMiddleware implements Middleware
echo "Test 2: CorsMiddleware implements Middleware... ";
try {
    $middleware = new CorsMiddleware('http://localhost:3000');
    if ($middleware instanceof \App\Core\Middleware) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 3: Middleware pipeline execution order
echo "Test 3: Middleware pipeline order... ";
try {
    $executionOrder = [];
    
    // Create test middleware
    $middleware1 = new class implements \App\Core\Middleware {
        private array $order;
        public function __construct() { $this->order = []; }
        public function setOrder(array &$ref): void { $this->order = &$ref; }
        public function handle(Request $request, \Closure $next): mixed {
            $this->order[] = 'middleware1_before';
            $result = $next($request);
            $this->order[] = 'middleware1_after';
            return $result;
        }
    };
    
    $middleware2 = new class implements \App\Core\Middleware {
        private array $order;
        public function __construct() { $this->order = []; }
        public function setOrder(array &$ref): void { $this->order = &$ref; }
        public function handle(Request $request, \Closure $next): mixed {
            $this->order[] = 'middleware2_before';
            $result = $next($request);
            $this->order[] = 'middleware2_after';
            return $result;
        }
    };
    
    $middleware1->setOrder($executionOrder);
    $middleware2->setOrder($executionOrder);
    
    // Simulate pipeline
    $pipeline = function($request) use ($middleware1, $middleware2, &$executionOrder) {
        return $middleware1->handle($request, function($req) use ($middleware2, &$executionOrder) {
            return $middleware2->handle($req, function($r) use (&$executionOrder) {
                $executionOrder[] = 'handler';
                return 'result';
            });
        });
    };
    
    $mockRequest = new Request('GET', '/', [], [], [], [], []);
    $pipeline($mockRequest);
    
    $expected = ['middleware1_before', 'middleware2_before', 'handler', 'middleware2_after', 'middleware1_after'];
    if ($executionOrder === $expected) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        echo "Expected: " . json_encode($expected) . "\n";
        echo "Got: " . json_encode($executionOrder) . "\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 4: AuthMiddleware throws exception on invalid token
echo "Test 4: AuthMiddleware throws on invalid token... ";
try {
    $middleware = new AuthMiddleware();
    $mockRequest = new Request('GET', '/', [], [], [], [], []);
    
    try {
        $middleware->handle($mockRequest, function($req) {
            return 'should not reach here';
        });
        echo "FAIL (Should have thrown exception)\n";
        exit(1);
    } catch (\App\Exceptions\UnauthorizedException $e) {
        echo "PASS\n";
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

echo "\nAll Middleware Tests Passed!\n";
?>
