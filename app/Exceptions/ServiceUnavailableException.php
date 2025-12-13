<?php
namespace App\Exceptions;

/**
 * 503 Service Unavailable
 * Used when a required dependency (e.g. database) is down/unreachable.
 */
class ServiceUnavailableException extends AppException {
    protected int $statusCode = 503;
}
?>

