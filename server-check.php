<?php
$envPath = __DIR__ . '/.env';
$items = array(
    'PHP version' => PHP_VERSION,
    '.env exists' => is_file($envPath) ? 'yes' : 'no',
    'pdo_mysql' => extension_loaded('pdo_mysql') ? 'yes' : 'no',
    'storage exists' => is_dir(__DIR__ . '/storage') ? 'yes' : 'no',
    'storage writable' => is_writable(__DIR__ . '/storage') ? 'yes' : 'no',
);
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Server Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; }
        table { border-collapse: collapse; width: 100%; max-width: 760px; }
        td, th { border: 1px solid #ddd; padding: 10px; text-align: left; }
    </style>
</head>
<body>
    <h1>Server Check</h1>
    <table>
        <?php foreach ($items as $name => $value): ?>
            <tr>
                <th><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></th>
                <td><?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>
