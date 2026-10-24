<?php
declare(strict_types=1);
if ((string) getenv('PRSPORT_DB_DSN') !== 'sqlite::memory:') throw new RuntimeException('Use isolated SQLite memory database');
require dirname(__DIR__) . '/app/bootstrap.php';
Auth::login(['email' => 'admin@rmutt.local', 'password' => 'Temp@1234']);

$sport = AppService::handle('sports.save', [
    'name' => 'วิ่ง 100 เมตร test', 'type' => 'individual', 'gender' => 'open',
    'min_players' => 1, 'max_players' => 1, 'icon' => 'running', 'scoring_type' => 'TIME',
]);
$tournament = AppService::handle('tournaments.save', ['name' => 'Track test', 'sport_ids' => [$sport['id']], 'format' => 'round_robin']);
$match = AppService::handle('matches.save', ['tournament_id' => $tournament['id'], 'sport_id' => $sport['id'], 'round_name' => '100m heat 1']);
$first = AppService::handle('matches.entrySave', ['match_id' => $match['id'], 'entry_type' => 'athlete',
    'department_code' => '130', 'display_name' => 'Runner 1', 'lane_number' => 1]);
$second = AppService::handle('matches.entrySave', ['match_id' => $match['id'], 'entry_type' => 'affiliation',
    'department_code' => '140', 'lane_number' => 2]);
$duplicateRejected = false;
try {
    AppService::handle('matches.entrySave', ['match_id' => $match['id'], 'entry_type' => 'athlete',
        'department_code' => '130', 'display_name' => 'Runner 3', 'lane_number' => 2]);
} catch (InvalidArgumentException $error) { $duplicateRejected = true; }
if (!$duplicateRejected) throw new RuntimeException('Duplicate lane accepted');

AppService::handle('matches.clock', ['match_id' => $match['id'], 'clock_action' => 'start']);
AppService::handle('matches.entryResult', ['id' => $first['id'], 'status' => 'FINISHED', 'provisional_time_ms' => 10820]);
AppService::handle('matches.entryResult', ['id' => $second['id'], 'status' => 'FINISHED', 'verified_time_ms' => 10560]);
$unverifiedRejected = false;
try { AppService::handle('matches.entriesConfirm', ['match_id' => $match['id']]); }
catch (InvalidArgumentException $error) { $unverifiedRejected = true; }
if (!$unverifiedRejected) throw new RuntimeException('Unverified time was confirmed');
AppService::handle('matches.entryResult', ['id' => $first['id'], 'status' => 'FINISHED',
    'provisional_time_ms' => 10820, 'verified_time_ms' => 10820, 'reason' => 'Verified from timing sheet']);
$entries = AppService::handle('matches.entries', ['match_id' => $match['id']]);
if ((int) $entries[0]['rank_number'] !== 2 || (int) $entries[1]['rank_number'] !== 1) throw new RuntimeException('Verified times were not ranked');
$confirmed = AppService::handle('matches.entriesConfirm', ['match_id' => $match['id']]);
if ($confirmed['status'] !== 'completed') throw new RuntimeException('Race was not confirmed');
$team1 = AppService::handle('teams.save', ['tournament_id' => $tournament['id'], 'sport_id' => $sport['id'], 'department_code' => '130', 'name' => 'Relay A']);
$team2 = AppService::handle('teams.save', ['tournament_id' => $tournament['id'], 'sport_id' => $sport['id'], 'department_code' => '140', 'name' => 'Relay B']);
$relay = AppService::handle('matches.save', ['tournament_id' => $tournament['id'], 'sport_id' => $sport['id'],
    'team1_id' => $team1['id'], 'team2_id' => $team2['id'], 'round_name' => 'ผลัด 4 × 100 เมตร']);
$relayCenter = AppService::handle('matches.center', ['match_id' => $relay['id']]);
if ($relayCenter['resolved_scoring_type'] !== 'TIME') throw new RuntimeException('Two-team relay incorrectly rendered as a team score');
AppService::handle('matches.entrySave', ['match_id' => $relay['id'], 'entry_type' => 'team', 'team_id' => $team1['id'], 'lane_number' => 1]);
AppService::handle('matches.entrySave', ['match_id' => $relay['id'], 'entry_type' => 'team', 'team_id' => $team2['id'], 'lane_number' => 2]);
if (count(AppService::handle('matches.entries', ['match_id' => $relay['id']])) !== 2) throw new RuntimeException('Relay teams were not available as race entrants');
echo "PASS multi-entrant heat, lane validation, provisional timing, ranking, confirmation\n";
