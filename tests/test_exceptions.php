<?php
/**
 * Test Exception Handling System
 * Tests custom exceptions and exception handler
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

use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ForbiddenException;

echo "Running Exception Tests...\n\n";

// Test 1: ValidationException
echo "Test 1: ValidationException... ";
try {
    $e = new ValidationException('Invalid input', ['field' => 'required']);
    if ($e->getStatusCode() === 400 && $e->getMessage() === 'Invalid input') {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 2: NotFoundException
echo "Test 2: NotFoundException... ";
try {
    $e = new NotFoundException('Resource not found');
    if ($e->getStatusCode() === 404) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 3: UnauthorizedException
echo "Test 3: UnauthorizedException... ";
try {
    $e = new UnauthorizedException();
    if ($e->getStatusCode() === 401 && $e->getMessage() === 'Unauthorized') {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 4: ForbiddenException
echo "Test 4: ForbiddenException... ";
try {
    $e = new ForbiddenException();
    if ($e->getStatusCode() === 403) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

// Test 5: Error details
echo "Test 5: Exception with error details... ";
try {
    $details = ['email' => 'Invalid format', 'password' => 'Too short'];
    $e = new ValidationException('Validation failed', $details);
    if ($e->getErrorDetails() === $details) {
        echo "PASS\n";
    } else {
        echo "FAIL\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "FAIL (" . $e->getMessage() . ")\n";
    exit(1);
}

echo "\nAll Exception Tests Passed!\n";
?>
