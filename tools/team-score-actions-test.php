<?php
declare(strict_types=1);
if ((string) getenv('PRSPORT_DB_DSN') !== 'sqlite::memory:') throw new RuntimeException('Use isolated SQLite memory database');
require dirname(__DIR__) . '/app/bootstrap.php';
Auth::login(['email' => 'admin@rmutt.local', 'password' => 'Temp@1234']);
$sport = AppService::handle('sports.save', ['name' => 'บาสเกตบอล test', 'type' => 'team', 'gender' => 'open',
    'min_players' => 5, 'max_players' => 5, 'icon' => 'basketball', 'scoring_type' => 'SCORE']);
AppService::handle('sportRules.save', ['sport_id' => $sport['id'], 'name' => 'คะแนนทีม +1/+2/+3',
    'scoring_type' => 'SCORE', 'quick_points' => '1,2,3']);
$tournament = AppService::handle('tournaments.save', ['name' => 'Basketball score test', 'sport_ids' => [$sport['id']], 'format' => 'round_robin']);
$home = AppService::handle('teams.save', ['tournament_id' => $tournament['id'], 'sport_id' => $sport['id'], 'department_code' => '130', 'name' => 'Home']);
$away = AppService::handle('teams.save', ['tournament_id' => $tournament['id'], 'sport_id' => $sport['id'], 'department_code' => '140', 'name' => 'Away']);
$match = AppService::handle('matches.save', ['tournament_id' => $tournament['id'], 'team1_id' => $home['id'], 'team2_id' => $away['id']]);
AppService::handle('matches.clock', ['match_id' => $match['id'], 'clock_action' => 'start']);
$center = AppService::handle('matches.center', ['match_id' => $match['id']]);
if ($center['rule']['scoring_type'] !== 'SCORE') throw new RuntimeException('Basketball rule mode incorrect');
$definition = null;
foreach ($center['rule']['event_types_data'] as $event) if ((int) ($event['points'] ?? 0) > 0) { $definition = $event; break; }
$event = AppService::handle('matches.event', ['match_id' => $match['id'], 'team_id' => $home['id'],
    'event_type' => $definition['code'], 'quick_team_score' => true, 'points' => 2,
    'request_id' => 'basketball_plus_two', 'expected_revision' => 0]);
if ((int) $event['points'] !== 2) throw new RuntimeException('Quick +2 was not recorded');
$corrected = AppService::handle('matches.scoreCorrection', ['match_id' => $match['id'], 'team_id' => $home['id'],
    'target_score' => 1, 'reason' => 'Scoreboard correction', 'expected_revision' => 1]);
if ((int) $corrected['team1_score'] !== 1) throw new RuntimeException('Score correction did not update score');
$correction = Database::pdo()->query('SELECT id FROM match_events ORDER BY id DESC LIMIT 1')->fetch();
AppService::handle('matches.eventVoid', ['id' => $correction['id'], 'expected_revision' => 2]);
$score = Database::pdo()->query('SELECT team1_score FROM matches WHERE id = ' . (int) $match['id'])->fetchColumn();
if ((int) $score !== 2) throw new RuntimeException('Voiding correction did not restore score');
echo "PASS +2 team score, audited correction, reversible result\n";
