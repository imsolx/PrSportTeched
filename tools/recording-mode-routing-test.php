<?php
declare(strict_types=1);
if ((string) getenv('PRSPORT_DB_DSN') !== 'sqlite::memory:') throw new RuntimeException('Use isolated SQLite memory database');
require dirname(__DIR__) . '/app/bootstrap.php';
Auth::login(['email' => 'admin@rmutt.local', 'password' => 'Temp@1234']);

try {
    AppService::handle('sports.save', ['name' => 'ไม่เลือกรูปแบบ', 'type' => 'team']);
    throw new RuntimeException('Sport without recording type was accepted');
} catch (InvalidArgumentException $error) {
    if (!str_contains($error->getMessage(), '12 รูปแบบ')) throw $error;
}

foreach (ScoringTypeDetector::SELECTABLE_TYPES as $index => $type) {
    // The name and icon deliberately do not determine the recording form.
    $sport = AppService::handle('sports.save', [
        'name' => "กีฬาแบบกำหนดเอง {$index}", 'type' => 'team', 'gender' => 'open',
        'min_players' => 1, 'max_players' => 3, 'icon' => 'football', 'scoring_type' => $type,
    ]);
    $rules = AppService::handle('sportRules.list', ['sport_id' => $sport['id']]);
    if (($rules[0]['scoring_type'] ?? '') !== $type) throw new RuntimeException("Rule mismatch for {$type}");
    $tournament = AppService::handle('tournaments.save', [
        'name' => "Tournament {$index}", 'sport_ids' => [$sport['id']], 'format' => 'round_robin',
    ]);
    $match = AppService::handle('matches.save', [
        'tournament_id' => $tournament['id'], 'sport_id' => $sport['id'], 'round_name' => 'ทดสอบ',
    ]);
    $center = AppService::handle('matches.center', ['match_id' => $match['id']]);
    if (($center['resolved_scoring_type'] ?? '') !== $type) throw new RuntimeException("Match routing mismatch for {$type}");
    if ($type !== 'SET') continue;
    AppService::handle('matches.recordingDraft', [
        'match_id' => $match['id'], 'scoring_type' => 'SET', 'expected_revision' => $center['match']['action_revision'],
        'result_data' => ['sets' => [['home' => 10, 'away' => 8]]],
    ]);
    $saved = AppService::handle('matches.center', ['match_id' => $match['id']]);
    if (($saved['recording_draft']['result_data']['sets'][0]['home'] ?? null) !== 10
        || $saved['match']['status'] !== 'scheduled') throw new RuntimeException('Draft incorrectly changed match status or data');
    try {
        AppService::handle('matches.recordingDraft', [
            'match_id' => $match['id'], 'scoring_type' => 'ROUND', 'result_data' => ['rounds' => []],
        ]);
        throw new RuntimeException('Mismatched draft type was accepted');
    } catch (InvalidArgumentException $error) {
        if (!str_contains($error->getMessage(), 'ไม่ตรง')) throw $error;
    }
}

$changeSport = AppService::handle('sports.save', [
    'name' => 'Editable recording mode', 'type' => 'team', 'gender' => 'open',
    'min_players' => 1, 'max_players' => 2, 'icon' => 'general', 'scoring_type' => 'SCORE',
]);
$oldTournament = AppService::handle('tournaments.save', [
    'name' => 'Before mode change', 'sport_ids' => [$changeSport['id']], 'format' => 'round_robin',
]);
$oldMatch = AppService::handle('matches.save', [
    'tournament_id' => $oldTournament['id'], 'sport_id' => $changeSport['id'], 'round_name' => 'Before',
]);
$liveOldMatch = AppService::handle('matches.save', [
    'tournament_id' => $oldTournament['id'], 'sport_id' => $changeSport['id'], 'round_name' => 'Already live',
]);
AppService::handle('matches.clock', [
    'match_id' => $liveOldMatch['id'], 'clock_action' => 'start', 'clock_seconds' => 0, 'period_number' => 1,
]);
$changed = AppService::handle('sports.save', array_replace($changeSport, [
    'id' => $changeSport['id'], 'scoring_type' => 'SET',
]));
if (($changed['scoring_type'] ?? '') !== 'SET') throw new RuntimeException('Updated sport did not return its new recording mode');
$rules = AppService::handle('sportRules.list', ['sport_id' => $changeSport['id']]);
if (count($rules) !== 2 || ($rules[0]['scoring_type'] ?? '') !== 'SET' || (int) ($rules[0]['is_active'] ?? 0) !== 1) {
    throw new RuntimeException('Changing a sport did not create and activate a new rule version');
}
$oldCenter = AppService::handle('matches.center', ['match_id' => $oldMatch['id']]);
if (($oldCenter['resolved_scoring_type'] ?? '') !== 'SCORE') throw new RuntimeException('Existing match rule changed retroactively');
$setting = AppService::handle('tournaments.sportSetting', [
    'tournament_id' => $oldTournament['id'], 'sport_id' => $changeSport['id'],
    'rule_version_id' => $rules[0]['id'], 'status' => 'ready',
]);
if ((int) ($setting['migrated_matches'] ?? 0) !== 1) {
    throw new RuntimeException('Untouched scheduled match was not migrated to the selected tournament rule');
}
$migratedCenter = AppService::handle('matches.center', ['match_id' => $oldMatch['id']]);
if (($migratedCenter['resolved_scoring_type'] ?? '') !== 'SET') {
    throw new RuntimeException('Migrated scheduled match did not route to SET');
}
$protectedCenter = AppService::handle('matches.center', ['match_id' => $liveOldMatch['id']]);
if (($protectedCenter['resolved_scoring_type'] ?? '') !== 'SCORE'
    || ($protectedCenter['match']['status'] ?? '') !== 'live') {
    throw new RuntimeException('Live match rule was changed by tournament setting migration');
}
$newTournament = AppService::handle('tournaments.save', [
    'name' => 'After mode change', 'sport_ids' => [$changeSport['id']], 'format' => 'round_robin',
]);
$newMatch = AppService::handle('matches.save', [
    'tournament_id' => $newTournament['id'], 'sport_id' => $changeSport['id'], 'round_name' => 'After',
]);
$newCenter = AppService::handle('matches.center', ['match_id' => $newMatch['id']]);
if (($newCenter['resolved_scoring_type'] ?? '') !== 'SET') throw new RuntimeException('New match did not use changed recording mode');

echo "PASS 12 explicit modes, version pinning, safe scheduled migration, protected live match, routing, draft, and mismatch rejection\n";
