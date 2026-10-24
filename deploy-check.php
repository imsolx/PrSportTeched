<?php
declare(strict_types=1);

$root = __DIR__;
$envPath = $root . DIRECTORY_SEPARATOR . '.env';
$checks = [];

function check_row(string $name, bool $ok, string $detail): array
{
    return ['name' => $name, 'ok' => $ok, 'detail' => $detail];
}

function read_env_values(string $path): array
{
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        $values[$key] = trim($value, "\"'");
    }
    return $values;
}

function env_value(array $env, string $key): ?string
{
    $value = $env[$key] ?? getenv($key);
    return $value === false || $value === '' ? null : (string) $value;
}

$env = read_env_values($envPath);
$checks[] = check_row('.env file', is_file($envPath), is_file($envPath) ? 'พบไฟล์ .env แล้ว' : 'ยังไม่พบไฟล์ .env ที่ htdocs/.env');
$checks[] = check_row('PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'PHP เปิด pdo_mysql แล้ว' : 'PHP ยังไม่มี pdo_mysql');
$checks[] = check_row('PDO PostgreSQL', extension_loaded('pdo_pgsql'), extension_loaded('pdo_pgsql') ? 'PHP เปิด pdo_pgsql แล้ว' : 'PHP ยังไม่มี pdo_pgsql');
$checks[] = check_row('app files', is_file($root . '/app/Database.php') && is_file($root . '/public/index.php'), 'ตรวจไฟล์ app/Database.php และ public/index.php');

$storage = $root . DIRECTORY_SEPARATOR . 'storage';
if (!is_dir($storage)) {
    @mkdir($storage, 0775, true);
}
$checks[] = check_row('storage writable', is_dir($storage) && is_writable($storage), is_dir($storage) && is_writable($storage) ? 'storage เขียนไฟล์ได้' : 'storage ยังเขียนไฟล์ไม่ได้');

$dsn = env_value($env, 'PRSPORT_DB_DSN');
$user = env_value($env, 'PRSPORT_DB_USER');
$pass = env_value($env, 'PRSPORT_DB_PASS');

if (!$dsn) {
    $databaseUrl = env_value($env, 'DATABASE_URL') ?: env_value($env, 'POSTGRES_URL') ?: env_value($env, 'SUPABASE_DB_URL');
    if ($databaseUrl) {
        $parts = parse_url($databaseUrl);
        if ($parts !== false && !empty($parts['host']) && !empty($parts['path'])) {
            $query = [];
            if (!empty($parts['query'])) {
                parse_str((string) $parts['query'], $query);
            }
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                (string) $parts['host'],
                (string) ($parts['port'] ?? 5432),
                ltrim((string) $parts['path'], '/'),
                (string) ($query['sslmode'] ?? env_value($env, 'DB_SSLMODE') ?: 'require')
            );
            $user = isset($parts['user']) ? rawurldecode((string) $parts['user']) : $user;
            $pass = isset($parts['pass']) ? rawurldecode((string) $parts['pass']) : $pass;
        }
    }
}

if (!$dsn) {
    $host = env_value($env, 'DB_HOST');
    $database = env_value($env, 'DB_DATABASE');
    $port = env_value($env, 'DB_PORT') ?: '3306';
    if ($host && $database) {
        $driver = env_value($env, 'DB_CONNECTION') ?: env_value($env, 'DB_DRIVER') ?: (str_contains($host, 'supabase') ? 'pgsql' : 'mysql');
        $dsn = in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)
            ? "pgsql:host={$host};port={$port};dbname={$database};sslmode=" . (env_value($env, 'DB_SSLMODE') ?: 'require')
            : "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $user = env_value($env, 'DB_USERNAME');
        $pass = env_value($env, 'DB_PASSWORD');
    }
}

$dbOk = false;
$dbDetail = 'ยังไม่ได้ตั้งค่า PRSPORT_DB_DSN หรือ DB_HOST/DB_DATABASE ใน .env';
if ($dsn && $user) {
    try {
        $pdo = new PDO($dsn, $user, $pass ?: '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $countSql = $driver === 'pgsql'
            ? "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = current_schema()"
            : 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()';
        $tableCount = (int) $pdo->query($countSql)->fetchColumn();
        $dbOk = true;
        $dbDetail = "เชื่อมต่อฐานข้อมูลได้ พบ {$tableCount} tables";
    } catch (Throwable $exception) {
        $dbDetail = $exception->getMessage();
    }
}
$checks[] = check_row('database connection', $dbOk, $dbDetail);

http_response_code($dbOk ? 200 : 500);
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PrSport Deploy Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; color: #17202a; background: #f6f8fb; }
        main { max-width: 860px; margin: 0 auto; background: #fff; border: 1px solid #d8dee8; border-radius: 8px; padding: 24px; }
        h1 { margin-top: 0; font-size: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 12px; border-bottom: 1px solid #e6eaf0; text-align: left; vertical-align: top; }
        .ok { color: #0f7a3b; font-weight: 700; }
        .fail { color: #b42318; font-weight: 700; }
        code { background: #eef2f7; padding: 2px 5px; border-radius: 4px; }
    </style>
</head>
<body>
<main>
    <h1>PrSport Deploy Check</h1>
    <p>ใช้หน้านี้ตรวจการเชื่อมต่อระหว่าง Render และ Supabase หลัง deploy แล้วควรจำกัดหรือลบไฟล์ <code>deploy-check.php</code> เมื่อระบบใช้งานได้ปกติ</p>
    <table>
        <thead>
        <tr><th>รายการ</th><th>สถานะ</th><th>รายละเอียด</th></tr>
        </thead>
        <tbody>
        <?php foreach ($checks as $check): ?>
            <tr>
                <td><?= htmlspecialchars($check['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="<?= $check['ok'] ? 'ok' : 'fail' ?>"><?= $check['ok'] ? 'OK' : 'FAIL' ?></td>
                <td><?= htmlspecialchars($check['detail'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</main>
</body>
</html>
