<?php
declare(strict_types=1);

if (getenv('PRSPORT_DB_DSN') !== 'sqlite::memory:') {
    throw new RuntimeException('Admin tests require isolated SQLite memory database');
}
require dirname(__DIR__) . '/app/bootstrap.php';

function adminCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function adminReject(callable $action, string $message, string $expectedError = ''): void
{
    try { $action(); } catch (InvalidArgumentException | RuntimeException $error) {
        if ($expectedError !== '' && !str_contains($error->getMessage(), $expectedError)) throw $error;
        return;
    }
    throw new RuntimeException($message);
}
function adminLogin(): array
{
    return Auth::login(['email' => 'admin@rmutt.local', 'password' => 'Temp@1234'])['user'];
}
function inviteToken(array $invite): string
{
    parse_str((string) parse_url($invite['invite_url'], PHP_URL_QUERY), $query);
    return (string) $query['invite'];
}

$admin = adminLogin();
$options = AppService::handle('users.options');
adminCheck(count($options['roles']) === 5, 'Five existing role keys must remain');
adminCheck($options['invite_expiry_minutes'] === 45, 'Default lifetime must remain 45 minutes');
adminCheck((string) Database::pdo()->query("SELECT value FROM settings WHERE key = 'staff_invite_expiry_minutes'")->fetchColumn() === '45', 'Lifetime must be stored in existing settings');
$self = ['id' => $admin['id'], 'email' => $admin['email'], 'name' => $admin['name'], 'role' => 'super_admin', 'is_active' => false];
adminReject(fn() => AppService::handle('users.save', $self), 'Self suspension must be rejected');
adminReject(fn() => AppService::handle('users.status', ['id' => $admin['id'], 'is_active' => false]), 'Status endpoint must reject self suspension');
adminReject(fn() => AppService::handle('users.delete', ['id' => $admin['id']]), 'Self deletion must be rejected');

$memberPayload = ['email' => 'manager@admin-test.local', 'name' => 'Manager', 'role' => 'sport_head', 'department_code' => '130', 'sport_ids' => '1,2', 'password' => 'Test@12345', 'password_confirm' => 'Test@12345', 'is_active' => true];
$member = AppService::handle('users.save', $memberPayload);
adminCheck(!isset($member['password_hash']) && !isset($member['password_salt']), 'User response must not expose password data');
$hashBefore = Database::pdo()->query('SELECT password_hash FROM users WHERE id = ' . (int) $member['id'])->fetchColumn();
$patch = $memberPayload;
unset($patch['password'], $patch['password_confirm']);
$patch['id'] = $member['id'];
$patch['name'] = 'Updated Manager';
$patch['is_active'] = false;
$updated = AppService::handle('users.save', $patch);
adminCheck(!$updated['is_active'] && $updated['sport_ids'] === '1,2' && $updated['department_code'] === '130', 'Status change must preserve role/scope');
adminCheck(Database::pdo()->query('SELECT password_hash FROM users WHERE id = ' . (int) $member['id'])->fetchColumn() === $hashBefore, 'Blank password update must preserve hash');
adminReject(fn() => Auth::login(['email' => $member['email'], 'password' => 'Test@12345']), 'Suspended member must not log in');
$patch['is_active'] = true;
AppService::handle('users.save', $patch);
adminReject(fn() => AppService::handle('users.save', $memberPayload), 'Duplicate email must fail');
adminReject(fn() => AppService::handle('users.save', array_replace($memberPayload, ['email' => 'bad@admin-test.local', 'role' => 'invented_role'])), 'Unknown roles must fail');
adminReject(fn() => AppService::handle('users.save', array_replace($memberPayload, ['email' => 'bad@admin-test.local', 'password_confirm' => 'Mismatch'])), 'Password mismatch must fail');

foreach (['admin', 'sport_head', 'result_officer', 'public_relations'] as $role) {
    $user = $role === 'sport_head' ? $member : AppService::handle('users.save', ['email' => $role . '@admin-test.local', 'name' => $role, 'role' => $role, 'password' => 'Test@12345']);
    $_SESSION['user_id'] = (int) $user['id'];
    foreach (['users.list', 'users.options', 'users.save', 'users.status', 'users.delete', 'invites.list', 'invites.create', 'invites.revoke', 'invites.toggle'] as $action) {
        adminReject(fn() => AppService::handle($action, ['id' => 1]), $role . ' must not access ' . $action, 'ไม่มีสิทธิ์');
    }
    adminLogin();
}
AppService::handle('users.status', ['id' => $member['id'], 'is_active' => false, 'role' => 'super_admin', 'sport_ids' => '999']);
$statusOnly = array_values(array_filter(AppService::handle('users.list'), fn(array $row) => $row['id'] === $member['id']))[0];
adminCheck($statusOnly['role'] === 'sport_head' && $statusOnly['sport_ids'] === '1,2' && $statusOnly['department_code'] === '130', 'Status endpoint must ignore role/scope fields');

$invitePayload = ['email' => 'invite@admin-test.local', 'name' => 'Invited Official', 'role' => 'result_officer', 'department_code' => '130', 'sport_ids' => '1'];
$invite = AppService::handle('invites.create', $invitePayload);
$token = inviteToken($invite);
adminCheck(abs(strtotime($invite['expires_at']) - time() - 45 * 60) < 5, 'Default invite expiry changed');
$listed = AppService::handle('invites.list');
adminCheck(!isset($listed[0]['token_hash']) && !isset($listed[0]['invite_url']), 'List must not expose tokens or reconstruct links');
AppService::handle('invites.toggle', ['enabled' => true, 'expiry_minutes' => 90]);
adminCheck(AppService::handle('users.options')['invite_expiry_minutes'] === 90, 'Lifetime setting must persist');
$unchanged = array_values(array_filter(AppService::handle('invites.list'), fn(array $row) => $row['id'] === $invite['id']))[0];
adminCheck($unchanged['expires_at'] === $invite['expires_at'], 'Settings must not alter existing invite expiry');
foreach ([0, 4, 1441, 'abc', 10.5] as $invalid) {
    adminReject(fn() => AppService::handle('invites.toggle', ['enabled' => false, 'expiry_minutes' => $invalid]), 'Invalid lifetime accepted');
}
adminCheck(AppService::handle('users.options')['staff_invites_enabled'], 'Rejected settings must not partially disable invites');
AppService::handle('invites.toggle', ['enabled' => false]);
adminReject(fn() => AppService::handle('invites.create', $invitePayload), 'Disabled invitation creation must fail');
adminReject(fn() => AppService::handle('invites.preview', ['token' => $token]), 'Disabled preview must fail');
adminReject(fn() => AppService::handle('invites.accept', ['token' => $token]), 'Disabled acceptance must fail');
adminCheck(AppService::handle('users.options')['invite_expiry_minutes'] === 90, 'Legacy toggle payload must preserve lifetime');
AppService::handle('invites.toggle', ['enabled' => true]);

$replacement = AppService::handle('invites.create', $invitePayload);
adminReject(fn() => AppService::handle('invites.preview', ['token' => $token]), 'Replaced token must fail');
adminCheck(abs(strtotime($replacement['expires_at']) - time() - 90 * 60) < 5, 'New invitations must use stored lifetime');
AppService::handle('invites.revoke', ['id' => $replacement['id']]);
adminReject(fn() => AppService::handle('invites.preview', ['token' => inviteToken($replacement)]), 'Revoked token must fail');

$expired = AppService::handle('invites.create', $invitePayload);
$stmt = Database::pdo()->prepare('UPDATE user_invites SET expires_at = :expires WHERE id = :id');
$stmt->execute([':expires' => '2000-01-01 00:00:00', ':id' => $expired['id']]);
adminReject(fn() => AppService::handle('invites.preview', ['token' => inviteToken($expired)]), 'Expired token must fail');
$expiredRow = array_values(array_filter(AppService::handle('invites.list'), fn(array $row) => $row['id'] === $expired['id']))[0];
adminCheck($expiredRow['status'] === 'expired', 'Expired status must persist');
$fresh = AppService::handle('invites.create', $invitePayload);
$accepted = AppService::handle('invites.accept', ['token' => inviteToken($fresh), 'email' => $fresh['email'], 'name' => 'Official', 'password' => 'Own@12345', 'password_confirm' => 'Own@12345']);
adminCheck($accepted['user']['role'] === 'result_officer' && $accepted['user']['sport_ids'] === '1', 'Invitation must preserve intended role/scope');
adminReject(fn() => AppService::handle('invites.accept', ['token' => inviteToken($fresh)]), 'One-time token must not be reusable');
adminLogin();
$all = AppService::handle('invites.list');
adminCheck(count(array_filter($all, fn(array $row) => $row['status'] === 'accepted')) === 1, 'Acceptance history must remain');
adminCheck(count(array_filter($all, fn(array $row) => $row['status'] === 'revoked')) === 1, 'Revocation history must remain');
adminCheck(count(array_filter($all, fn(array $row) => $row['status'] === 'expired')) === 1, 'Expired history must remain');
AppService::handle('users.delete', ['id' => $member['id']]);
adminCheck(count(array_filter(AppService::handle('users.list'), fn(array $row) => $row['id'] === $member['id'])) === 0, 'Non-self deletion must remain available');
echo "PASS admin CRUD, role/scope preservation, self protection, server authorization, stored lifetime, invitation states and one-time tokens\n";
