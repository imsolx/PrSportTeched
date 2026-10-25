<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = Auth::currentUser();
$allowed = in_array((string) ($user['role'] ?? ''), [
    Auth::ROLE_SUPER_ADMIN,
    Auth::ROLE_ADMIN,
    Auth::ROLE_RESULT_OFFICER,
    Auth::ROLE_SPORT_HEAD,
], true);

$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$appBase = preg_replace('#/public/scoring$#', '', $scriptDir) ?? '';
$assetBase = $appBase . '/public/assets';
$backUrl = $appBase . '/?view=official';

require __DIR__ . '/../pages/scoring/SetRecorder.php';
