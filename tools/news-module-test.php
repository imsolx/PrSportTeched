<?php
declare(strict_types=1);

// Regression tests use the real service and schema, but never the configured database.
if (getenv('PRSPORT_DB_DSN') !== 'sqlite::memory:') {
    throw new RuntimeException('News tests require isolated SQLite memory database');
}
putenv('GEMINI_API_KEY=');
putenv('GEMINI_MODEL=local-summary');
require dirname(__DIR__) . '/app/bootstrap.php';

function newsCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function newsReject(callable $action, string $expected): void
{
    try { $action(); } catch (InvalidArgumentException | RuntimeException $error) {
        newsCheck(str_contains($error->getMessage(), $expected), $error->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $expected);
}
function newsAdmin(): array
{
    return Auth::login(['email' => 'admin@rmutt.local', 'password' => 'Temp@1234'])['user'];
}
function newsById(int $id): array
{
    return AppService::handle('public.news', ['id' => $id]);
}

$protected = ['news.list', 'news.save', 'news.publish', 'news.delete', 'news.upload', 'matches.forNews', 'ai.news'];
newsCheck(AppService::handle('public.news') === AppService::handle('getPublishedNews'), 'Published news alias changed');
foreach ($protected as $action) newsReject(fn() => AppService::handle($action, ['id' => 1]), 'ไม่มีสิทธิ์');

$admin = newsAdmin();
$payload = ['title' => 'News regression draft', 'slug' => 'news-regression-draft', 'excerpt' => 'คำโปรย', 'body' => "ย่อหน้าแรก\n\nย่อหน้าที่สอง", 'source_url' => 'https://www.rmutt.ac.th/', 'status' => 'draft', 'is_pinned' => true];
$created = AppService::handle('news.save', $payload);
$id = (int) $created['id'];
newsCheck(newsById($id) === [], 'Draft must not be public');
newsCheck($created['author_email'] === $admin['email'] && $created['status'] === 'draft', 'Author/status changed');
$listed = array_values(array_filter(AppService::handle('news.list'), fn(array $row): bool => (int) $row['id'] === $id))[0];
foreach (['news_type_label', 'department_name', 'sport_id', 'tournament_id', 'team_id', 'match_id'] as $key) {
    newsCheck(array_key_exists($key, $listed), 'Enriched field missing: ' . $key);
}
newsCheck(AppService::handle('news.list') === AppService::handle('listNews'), 'List alias changed');
$published = AppService::handle('publishNews', ['id' => $id]);
newsCheck($published['status'] === 'published' && $published['published_at'] !== '', 'Publication changed');
newsCheck(count(newsById($id)) === 1, 'Published news must be public');
$second = AppService::handle('saveNews', ['args' => [array_replace($payload, ['title' => 'Unpinned future news', 'slug' => 'news-regression-future', 'status' => 'published', 'published_at' => '2099-01-01 00:00:00', 'is_pinned' => false])]]);
newsCheck((int) AppService::handle('public.news')[0]['id'] === $id, 'Pinned news must sort before newer unpinned news');

$hall = AppService::handle('news.save', array_replace($payload, ['title' => 'Hall regression', 'slug' => 'news-regression-hall', 'news_type' => 'hall_of_fame', 'status' => 'published']));
newsCheck(count(newsById((int) $hall['id'])) === 1, 'Hall article must resolve by ID');
newsCheck(!in_array((int) $hall['id'], array_map(fn(array $row): int => (int) $row['id'], AppService::handle('public.news')), true), 'Hall must stay separate from regular news');
newsCheck(in_array((int) $hall['id'], array_map(fn(array $row): int => (int) $row['id'], AppService::handle('public.hall')), true), 'Hall feed must remain available');

foreach (['draft', 'archived'] as $status) {
    $updated = AppService::handle('news.save', array_replace($payload, ['id' => $id, 'status' => $status, 'is_pinned' => false]));
    newsCheck($updated['status'] === $status && (int) $updated['is_pinned'] === 0, 'Edit status/pin changed');
    newsCheck(newsById($id) === [], 'Unpublished/archived news must not be public');
}
newsReject(fn() => AppService::handle('news.save', ['title' => '']), 'กรุณากรอกหัวข้อข่าว');
newsReject(fn() => AppService::handle('news.save', array_replace($payload, ['status' => 'unknown'])), 'สถานะข่าวไม่ถูกต้อง');
newsReject(fn() => AppService::handle('news.save', array_replace($payload, ['source_url' => 'javascript:alert(1)'])), 'ลิงก์เว็บไซต์');
newsReject(fn() => AppService::handle('news.upload', ['data_url' => 'data:text/plain;base64,dGVzdA==']), 'ไฟล์รูปภาพไม่ถูกต้อง');
newsReject(fn() => AppService::handle('ai.news', ['story_type' => 'unknown']), 'รูปแบบข่าวไม่ถูกต้อง');
newsReject(fn() => AppService::handle('ai.news', ['tone' => 'unknown']), 'โทนข่าวไม่ถูกต้อง');
newsReject(fn() => AppService::handle('ai.news', ['instruction' => str_repeat('ก', 801)]), '800');
newsReject(fn() => AppService::handle('ai.news', ['instruction' => '[GENERAL_TOPIC]']), 'กรุณาระบุหัวข้อ');
$topic = 'เชิญร่วมกิจกรรมกีฬาเพื่อสุขภาพ โปรดติดตามกำหนดการจากคณะ';
$topicDraft = AppService::handle('ai.news', ['story_type' => 'preview', 'tone' => 'official', 'instruction' => "[GENERAL_TOPIC]\n{$topic}\n\nประเด็นเน้น: เชิญชวนผู้ชม\nความยาว: สั้น"]);
newsCheck($topicDraft['body'] === $topic && $topicDraft['match_id'] === '', 'General topic must not borrow a match or invent facts');
newsCheck($topicDraft['provider'] === 'local-summary', 'Missing Gemini credentials must be represented honestly');
newsCheck(array_keys($topicDraft) === ['title', 'excerpt', 'body', 'banner_title', 'banner_subtitle', 'sport_name', 'tournament_name', 'match_id', 'model', 'provider'], 'AI response contract changed');

foreach (['admin', 'public_relations', 'sport_head', 'result_officer'] as $role) {
    newsAdmin();
    $user = AppService::handle('users.save', ['email' => $role . '@news-test.local', 'name' => $role, 'role' => $role, 'password' => 'Test@12345']);
    $_SESSION['user_id'] = (int) $user['id'];
    if (in_array($role, ['sport_head', 'result_officer'], true)) {
        foreach ($protected as $action) newsReject(fn() => AppService::handle($action, ['id' => $id]), 'ไม่มีสิทธิ์');
    } else {
        newsCheck(count(AppService::handle('news.list')) >= 3, $role . ' must retain news access');
        $own = AppService::handle('news.save', ['title' => $role, 'slug' => 'news-role-' . $role]);
        newsCheck($own['author_email'] === $user['email'], 'Role author must remain unchanged');
        AppService::handle('news.publish', ['id' => $own['id']]);
        newsCheck(count(newsById((int) $own['id'])) === 1, $role . ' must retain publication access');
        AppService::handle('news.delete', ['id' => $own['id']]);
        newsCheck(newsById((int) $own['id']) === [], 'Deleted article must not resolve');
    }
}
newsAdmin();
AppService::handle('deleteNews', ['id' => $id]);
AppService::handle('news.delete', ['id' => $second['id']]);
AppService::handle('news.delete', ['id' => $hall['id']]);
newsCheck(newsById($id) === [], 'Deletion alias changed');
echo "PASS: news CRUD, legacy aliases, response fields, draft/publish/archive, pin sorting, Hall feed, validation, and server permissions for all roles (isolated SQLite).\n";
