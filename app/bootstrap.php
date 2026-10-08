<?php
declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
define('DATABASE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'database');
define('STORAGE_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'storage');
define('UPLOAD_PATH', PUBLIC_PATH . DIRECTORY_SEPARATOR . 'uploads');

load_env_file(ROOT_PATH . DIRECTORY_SEPARATOR . '.env');

date_default_timezone_set(getenv('PRSPORT_TIMEZONE') ?: 'Asia/Bangkok');

spl_autoload_register(function (string $class): void {
    $file = APP_PATH . DIRECTORY_SEPARATOR . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    $sessionPath = STORAGE_PATH . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0775, true);
    }
    session_save_path($sessionPath);
    session_name('prsport_session');
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
    ]);
}

Database::boot();

function app_url(string $path = ''): string
{
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($base === '/' || $base === '\\') {
        $base = '';
    }
    $path = ltrim($path, '/');
    if (str_ends_with($base, '/public') && str_starts_with($path, 'public/')) {
        $path = substr($path, strlen('public/'));
    }
    return $base . '/' . $path;
}

function versioned_asset_url(string $path): string
{
    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    $file = ROOT_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $version = is_file($file) ? (string) filemtime($file) : '1';
    return app_url($normalized) . '?v=' . rawurlencode($version);
}

function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '' || getenv($key) !== false) {
            continue;
        }
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
    }
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function request_payload(): array
{
    $raw = file_get_contents('php://input');
    $json = [];
    if ($raw !== false && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $json = $decoded;
        }
    }
    return array_replace($_POST, $json);
}

function require_csrf_for_write(string $action): void
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET' || in_array($action, ['auth.login', 'invites.preview', 'invites.accept'], true)) {
        return;
    }
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(Auth::csrfToken(), (string) $token)) {
        throw new RuntimeException('CSRF token ไม่ถูกต้อง');
    }
}
