<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$action = (string) ($_GET['action'] ?? ($_POST['action'] ?? ''));

try {
    if ($action === '') {
        throw new InvalidArgumentException('กรุณาระบุ action');
    }

    require_csrf_for_write($action);
    $payload = request_payload();
    unset($payload['action']);
    $data = AppService::handle($action, $payload, $_FILES);
    json_response(['ok' => true, 'data' => $data]);
} catch (InvalidArgumentException $error) {
    json_response(['ok' => false, 'error' => $error->getMessage()], 422);
} catch (RuntimeException $error) {
    $status = str_contains($error->getMessage(), 'ไม่มีสิทธิ์') ? 403 : 400;
    json_response(['ok' => false, 'error' => $error->getMessage()], $status);
} catch (Throwable $error) {
    error_log((string) $error);
    json_response(['ok' => false, 'error' => 'เกิดข้อผิดพลาดภายในระบบ'], 500);
}

