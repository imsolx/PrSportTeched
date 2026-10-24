<?php
declare(strict_types=1);

if ((string) getenv('PRSPORT_DB_DSN') !== 'sqlite::memory:') {
    throw new RuntimeException('Use an isolated in-memory SQLite database');
}
require dirname(__DIR__) . '/app/bootstrap.php';

Auth::login(['email' => 'admin@rmutt.local', 'password' => 'Temp@1234']);
$pdo = Database::pdo();
$now = Database::now();
$hash = password_hash('Test@12345', PASSWORD_DEFAULT);
$insert = $pdo->prepare('INSERT INTO users (id, email, name, role, department_code, sport_ids, password_hash, is_active, created_at)
    VALUES (:id, :email, :name, :role, :department, :sports, :hash, 1, :created)');
foreach ([
    [901, 'official@test.local', 'Official A', 'result_officer', '130', '2'],
    [902, 'manager@test.local', 'Manager B', 'sport_head', '140', '2'],
    [903, 'outside@test.local', 'Outside', 'result_officer', '150', '2'],
] as [$id, $email, $name, $role, $department, $sports]) {
    $insert->execute([':id' => $id, ':email' => $email, ':name' => $name, ':role' => $role,
        ':department' => $department, ':sports' => $sports, ':hash' => $hash, ':created' => $now]);
}
$insertStudent = $pdo->prepare('INSERT INTO students (id, student_code, first_name, last_name, department_code, created_at, updated_at)
    VALUES (:id, :code, :first_name, :last_name, :department, :created, :updated)');
foreach ([
    [9901, 'SCOPE-140', 'Manager', 'Scope', '140'],
    [9902, 'SCOPE-130', 'Outside', 'Scope', '130'],
] as [$id, $code, $firstName, $lastName, $department]) {
    $insertStudent->execute([':id' => $id, ':code' => $code, ':first_name' => $firstName, ':last_name' => $lastName,
        ':department' => $department, ':created' => $now, ':updated' => $now]);
}
$tournament = AppService::handle('tournaments.save', ['name' => 'Assignment test', 'sport_ids' => [2], 'format' => 'round_robin']);
$home = AppService::handle('teams.save', ['tournament_id' => $tournament['id'], 'sport_id' => 2, 'department_code' => '130', 'name' => 'Home']);
$away = AppService::handle('teams.save', ['tournament_id' => $tournament['id'], 'sport_id' => 2, 'department_code' => '140', 'name' => 'Away']);
$match = AppService::handle('matches.save', ['tournament_id' => $tournament['id'], 'team1_id' => $home['id'], 'team2_id' => $away['id']]);

$outsideRejected = false;
try {
    AppService::handle('matches.assignOfficials', ['match_id' => $match['id'], 'assignments' => [['user_id' => 903]]]);
} catch (InvalidArgumentException $error) {
    $outsideRejected = true;
}
if (!$outsideRejected) throw new RuntimeException('Assignment outside department was accepted');

AppService::handle('matches.assignOfficials', ['match_id' => $match['id'], 'assignments' => [
    ['user_id' => 901, 'team_id' => $home['id']],
    ['user_id' => 902, 'team_id' => $away['id']],
]]);
Auth::logout();
Auth::login(['email' => 'official@test.local', 'password' => 'Test@12345']);
if (count(AppService::handle('matches.list', ['tournament_id' => ''])) !== 1) throw new RuntimeException('Assigned official cannot list match');
if (count(AppService::handle('teams.list', ['tournament_id' => $tournament['id']])) !== 1) throw new RuntimeException('Official team list escaped department scope');
AppService::handle('matches.center', ['match_id' => $match['id']]);
Auth::logout();
Auth::login(['email' => 'manager@test.local', 'password' => 'Test@12345']);
if (count(AppService::handle('matches.list', ['tournament_id' => ''])) !== 1) throw new RuntimeException('Assigned manager cannot list match');
$visibleStudents = AppService::handle('students.list', []);
if (count(array_filter($visibleStudents, static fn(array $student): bool => (string) $student['student_code'] === 'SCOPE-140')) !== 1) {
    throw new RuntimeException('Manager cannot view students in the assigned department');
}
if (count(array_filter($visibleStudents, static fn(array $student): bool => (string) $student['student_code'] === 'SCOPE-130')) !== 0) {
    throw new RuntimeException('Manager student view escaped department scope');
}
AppService::handle('matches.center', ['match_id' => $match['id']]);
$outsideEditRejected = false;
try { AppService::handle('teams.save', ['id' => $home['id'], 'tournament_id' => $tournament['id'],
    'sport_id' => 2, 'department_code' => '130', 'name' => 'Changed by outside manager']); }
catch (RuntimeException $error) { $outsideEditRejected = true; }
if (!$outsideEditRejected) throw new RuntimeException('Manager edited outside team');

echo "PASS match assignment, department scope, and role access\n";
