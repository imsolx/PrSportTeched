<?php
declare(strict_types=1);

// Deliberately fail before action dispatch; no real database is contacted.
putenv('PRSPORT_DB_DSN=unsupported_test_driver:');
ob_start();
register_shutdown_function(static function (): void {
    $body = (string) ob_get_clean();
    $payload = json_decode($body, true);
    if (http_response_code() !== 500 || !is_array($payload)
        || ($payload['ok'] ?? null) !== false || empty($payload['error'])
        || str_contains($body, 'Stack trace') || str_contains($body, 'PDOException')) {
        fwrite(STDERR, "FAIL bootstrap error must return sanitized JSON and HTTP 500\n");
        exit(1);
    }
    echo "PASS bootstrap database failure returns sanitized JSON and HTTP 500\n";
});
require dirname(__DIR__) . '/public/api.php';
