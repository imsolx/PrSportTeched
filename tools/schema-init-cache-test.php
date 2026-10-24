<?php
declare(strict_types=1);

// Exercise the PostgreSQL initialization gate without production credentials.
define('DATABASE_PATH', dirname(__DIR__) . '/database');
define('STORAGE_PATH', sys_get_temp_dir() . '/prsport-schema-test-' . bin2hex(random_bytes(8)));
require dirname(__DIR__) . '/app/Database.php';
mkdir(STORAGE_PATH, 0700);

final class SchemaGateProbe extends PDO
{
    public int $attempts = 0;
    public function __construct() {}
    public function getAttribute(int $attribute): mixed { return 'pgsql'; }
    public function exec(string $statement): int|false
    {
        $this->attempts++;
        throw new RuntimeException('Simulated migration failure');
    }
}

$dsn = 'pgsql:host=test.invalid;dbname=test';
$user = 'test';
$marker = STORAGE_PATH . '/schema-' . hash('sha256', $dsn . '|' . $user) . '.ready';
$method = new ReflectionMethod(Database::class, 'initializeSchema');
$method->setAccessible(true);
$pdo = new SchemaGateProbe();
try {
    for ($attempt = 1; $attempt <= 2; $attempt++) {
        try {
            $method->invoke(null, $pdo, $dsn, $user);
            throw new RuntimeException('Expected migration failure');
        } catch (RuntimeException $error) {
            if ($error->getMessage() !== 'Simulated migration failure') throw $error;
        }
        if ($pdo->attempts !== $attempt || trim((string) file_get_contents($marker)) !== '') {
            throw new RuntimeException('Failed migration must remain retryable');
        }
    }
    $signature = hash('sha256', implode('|', [
        $dsn, $user,
        hash_file('sha256', dirname(__DIR__) . '/app/Database.php'),
        hash_file('sha256', DATABASE_PATH . '/schema.pgsql.sql'),
        hash_file('sha256', DATABASE_PATH . '/seed.pgsql.sql'),
    ]));
    file_put_contents($marker, $signature);
    $method->invoke(null, $pdo, $dsn, $user);
    $method->invoke(null, $pdo, $dsn, $user);
    if ($pdo->attempts !== 2) throw new RuntimeException('Current schema must skip migration');
    file_put_contents($marker, 'old-schema');
    try {
        $method->invoke(null, $pdo, $dsn, $user);
        throw new RuntimeException('Expected stale schema migration');
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'Simulated migration failure') throw $error;
    }
    if ($pdo->attempts !== 3) throw new RuntimeException('Stale marker must run migration');
    echo "PASS schema initialization cache, failed migration retry, and stale marker invalidation\n";
} finally {
    if (is_file($marker)) unlink($marker);
    rmdir(STORAGE_PATH);
}
