<?php
declare(strict_types=1);
if ((string) getenv('PRSPORT_DB_DSN') !== 'sqlite::memory:') throw new RuntimeException('Use isolated SQLite memory database');
require dirname(__DIR__) . '/app/bootstrap.php';
Auth::login(['email' => 'admin@rmutt.local', 'password' => 'Temp@1234']);
$tournament = AppService::handle('tournaments.save', ['name' => 'Event request test', 'sport_ids' => [1], 'format' => 'round_robin']);
$home = AppService::handle('teams.save', ['tournament_id' => $tournament['id'], 'sport_id' => 1, 'department_code' => '130', 'name' => 'Home']);
$away = AppService::handle('teams.save', ['tournament_id' => $tournament['id'], 'sport_id' => 1, 'department_code' => '140', 'name' => 'Away']);
$match = AppService::handle('matches.save', ['tournament_id' => $tournament['id'], 'team1_id' => $home['id'], 'team2_id' => $away['id']]);
AppService::handle('matches.clock', ['match_id' => $match['id'], 'clock_action' => 'start']);
$center = AppService::handle('matches.center', ['match_id' => $match['id']]);
$definition = null;
foreach ($center['rule']['event_types_data'] as $event) {
    if ((int) ($event['points'] ?? 0) > 0) { $definition = $event; break; }
}
if (!$definition) throw new RuntimeException('Scoring event not configured');
$payload = ['match_id' => $match['id'], 'event_type' => $definition['code'], 'team_id' => $home['id'],
    'quick_team_score' => true, 'points' => 1, 'request_id' => 'test_request_123456', 'expected_revision' => 0];
$first = AppService::handle('matches.event', $payload);
$again = AppService::handle('matches.event', $payload);
if ((int) $first['id'] !== (int) $again['id']) throw new RuntimeException('Duplicate event was inserted');
$count = (int) Database::pdo()->query('SELECT COUNT(*) FROM match_events')->fetchColumn();
if ($count !== 1) throw new RuntimeException('Event count is not 1');
$stale = false;
try { AppService::handle('matches.event', array_replace($payload, ['request_id' => 'test_request_654321'])); }
catch (RuntimeException $error) { $stale = str_contains($error->getMessage(), 'รีเฟรช'); }
if (!$stale) throw new RuntimeException('Stale event revision accepted');
echo "PASS idempotent quick score and stale revision rejection\n";
