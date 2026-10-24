<?php
declare(strict_types=1);

if (!str_starts_with((string) getenv('PRSPORT_DB_DSN'), 'sqlite:')) {
    throw new RuntimeException('Integration test requires an isolated SQLite database');
}

require dirname(__DIR__) . '/app/bootstrap.php';

Auth::login(['email' => 'admin@rmutt.local', 'password' => 'Temp@1234']);

$rule = AppService::handle('sportRules.save', [
    'sport_id' => 2,
    'name' => 'Best of 3 integration test',
    'player_type' => 'team',
    'team_size' => 6,
    'scoring_type' => 'SET',
    'best_of' => 3,
    'points_to_win' => 21,
    'set_win_by' => 2,
    'max_points' => 30,
    'include_in_standings' => true,
    'is_active' => true,
]);

$tournament = AppService::handle('tournaments.save', [
    'name' => 'Multi-sport integration test',
    'sport_ids' => [2],
    'format' => 'round_robin',
    'status' => 'data_entry',
]);

$team1 = AppService::handle('teams.save', ['tournament_id' => $tournament['id'], 'sport_id' => 2, 'department_code' => '130', 'name' => 'Test A']);
$team2 = AppService::handle('teams.save', ['tournament_id' => $tournament['id'], 'sport_id' => 2, 'department_code' => '140', 'name' => 'Test B']);

AppService::handle('tournaments.sportSetting', [
    'tournament_id' => $tournament['id'],
    'sport_id' => 2,
    'rule_version_id' => $rule['id'],
    'status' => 'ready',
]);

$match = AppService::handle('matches.save', [
    'tournament_id' => $tournament['id'],
    'team1_id' => $team1['id'],
    'team2_id' => $team2['id'],
    'round_name' => 'รอบทดสอบ',
]);

AppService::handle('matches.clock', [
    'match_id' => $match['id'],
    'clock_action' => 'start',
    'clock_seconds' => 0,
    'period_number' => 1,
]);
$setQuickScoreRejected = false;
try {
    AppService::handle('matches.event', [
        'match_id' => $match['id'],
        'event_type' => 'score',
        'team_id' => $team1['id'],
        'points' => 1,
        'quick_team_score' => true,
    ]);
} catch (InvalidArgumentException $error) {
    $setQuickScoreRejected = str_contains($error->getMessage(), 'ไม่รองรับปุ่มเพิ่มคะแนนทีม');
}
if (!$setQuickScoreRejected) {
    throw new RuntimeException('SET sport incorrectly accepted a team quick-score event');
}

$first = AppService::handle('matches.result', [
    'match_id' => $match['id'],
    'result_data' => json_encode(['sets' => [
        ['home' => 21, 'away' => 17],
        ['home' => 18, 'away' => 21],
        ['home' => 21, 'away' => 15],
    ]]),
]);
if ((int) $first['team1_score'] !== 2 || (int) $first['team2_score'] !== 1 || (int) $first['winner_team_id'] !== (int) $team1['id']) {
    throw new RuntimeException('SET result was not persisted correctly');
}

$second = AppService::handle('matches.result', [
    'match_id' => $match['id'],
    'reason' => 'แก้คะแนนเซตที่สองจากใบบันทึกผู้ตัดสิน',
    'result_data' => json_encode(['sets' => [
        ['home' => 21, 'away' => 17],
        ['home' => 21, 'away' => 19],
    ]]),
]);
if ((int) $second['team1_score'] !== 2 || (int) $second['team2_score'] !== 0) {
    throw new RuntimeException('Revised SET result was not persisted correctly');
}

$detail = AppService::handle('matches.resultDetail', ['match_id' => $match['id']]);
if ((int) ($detail['result']['revision_number'] ?? 0) !== 2) {
    throw new RuntimeException('Result revision number was not incremented');
}
$revisionCount = (int) Database::pdo()->query('SELECT COUNT(*) FROM match_result_revisions')->fetchColumn();
if ($revisionCount !== 1) {
    throw new RuntimeException('Result revision history was not created');
}
$staleRejected = false;
try {
    AppService::handle('matches.result', [
        'match_id' => $match['id'], 'expected_revision' => 1, 'reason' => 'stale editor',
        'result_data' => json_encode(['sets' => [['home' => 21, 'away' => 10], ['home' => 21, 'away' => 11]]]),
    ]);
} catch (RuntimeException $error) {
    $staleRejected = str_contains($error->getMessage(), 'รีเฟรช');
}
if (!$staleRejected) throw new RuntimeException('Stale result revision was accepted');

$running = AppService::handle('sports.save', [
    'name' => 'วิ่ง 200 เมตร integration test',
    'type' => 'individual',
    'gender' => 'open',
    'min_players' => 1,
    'max_players' => 1,
    'reserve_players' => 0,
    'icon' => 'running',
    'scoring_type' => 'TIME',
]);
$runningRules = AppService::handle('sportRules.list', ['sport_id' => $running['id']]);
if (($runningRules[0]['scoring_type'] ?? '') !== 'TIME') {
    throw new RuntimeException('The sport did not retain its selected recording type');
}

$overrideMatch = AppService::handle('matches.save', [
    'tournament_id' => $tournament['id'],
    'team1_id' => $team1['id'],
    'team2_id' => $team2['id'],
    'round_name' => 'ทดสอบสลับมุมมอง',
]);
$override = AppService::handle('matches.result', [
    'match_id' => $overrideMatch['id'],
    'scoring_type' => 'RACE_POSITION',
    'result_data' => json_encode(['home' => ['value' => 1, 'status' => 'VALID'], 'away' => ['value' => 2, 'status' => 'VALID']]),
]);
if (($override['result']['scoring_type'] ?? '') !== 'RACE_POSITION' || (int) $override['winner_team_id'] !== (int) $team1['id']) {
    throw new RuntimeException('Per-result scoring type switch was not persisted');
}

echo "PASS multi-sport persistence and revision audit\n";
